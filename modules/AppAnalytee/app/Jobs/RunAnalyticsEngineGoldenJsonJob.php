<?php

namespace Modules\AppAnalytee\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AppAnalytee\Services\GoldenJsonV1Serializer;
use Symfony\Component\Process\Process;

class RunAnalyticsEngineGoldenJsonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $teamId,
        public int $accountId
    ) {}

    public function handle(): void
    {
        $teamId = (int) $this->teamId;
        $accountId = (int) $this->accountId;

        if ($teamId <= 0 || $accountId <= 0) {
            throw new \RuntimeException('team_id/account_id inválidos');
        }

        $timeout = (int) (env('ANALYTEE_ENGINE_TIMEOUT', 3600) ?: 3600);
        $ttlSeconds = max(60, $timeout + 300);
        $lockKey = "analytee:engine:run:{$teamId}:{$accountId}";

        if (! Cache::add($lockKey, Carbon::now()->toDateTimeString(), $ttlSeconds)) {
            throw new \RuntimeException('Ejecución concurrente activa (lock)');
        }

        try {
            if ($this->shouldUseFixtures()) {
                $this->handleWithFixtures();

                return;
            }

            try {
                DB::table('analytee_accounts')
                    ->where('team_id', $teamId)
                    ->where('account_id', $accountId)
                    ->update([
                        'status' => 'analysis_running',
                        'updated' => time(),
                    ]);
            } catch (\Throwable) {
            }

            $account = DB::table('analytee_accounts')
                ->where('team_id', $teamId)
                ->where('account_id', $accountId)
                ->first([
                    'place_id',
                    'rating',
                    'user_ratings_total',
                    'website',
                    'types',
                    'vicinity',
                ]);

            if (! $account) {
                throw new \RuntimeException('analytee_accounts no encontrado para este team/account');
            }

            $placeId = trim((string) ($account->place_id ?? ''));
            if ($placeId === '') {
                throw new \RuntimeException('place_id no disponible (abortado)');
            }

            $relativeDir = "analytee/exports/{$teamId}/{$accountId}";
            $relativeInputPath = "{$relativeDir}/input.json";
            $absoluteInputPath = $this->localAbsPath($relativeInputPath);

            try {
                if (! is_file($absoluteInputPath)) {
                    throw new \RuntimeException('input.json no existe (prepara dataset antes de ejecutar)');
                }

                $jsonString = (string) @file_get_contents($absoluteInputPath);
                if ($jsonString === '') {
                    throw new \RuntimeException('input.json vacío o ilegible');
                }

                $decoded = json_decode($jsonString, true);
                if (! is_array($decoded)) {
                    throw new \RuntimeException('input.json inválido (JSON)');
                }

                $serializer = new GoldenJsonV1Serializer;
                $serializer->validate($decoded);
                $golden = $decoded;

                if (app()->environment('production') && count((array) ($golden['reviews'] ?? [])) === 0) {
                    throw new \RuntimeException('reviews vacío (abortado)');
                }

                $engineResult = $this->runEngine($absoluteInputPath);
                $outputInfo = $this->verifyEngineOutputs($absoluteInputPath);
                $storedReports = $this->storeEngineOutputsToLocal($relativeDir, $outputInfo);

                $referenceInfo = $this->compareWithReferenceIfPresent($relativeDir, $golden);

                $runSummary = [
                    'team_id' => $teamId,
                    'account_id' => $accountId,
                    'place_id' => $placeId,
                    'input_json_relative_path' => $relativeInputPath,
                    'input_json_sha256' => hash('sha256', $jsonString),
                    'reviews_count_input' => count((array) ($golden['reviews'] ?? [])),
                    'reviews_count_output' => count((array) ($golden['reviews'] ?? [])),
                    'engine' => $engineResult,
                    'outputs' => $outputInfo,
                    'stored_reports' => $storedReports,
                    'reference_comparison' => $referenceInfo,
                    'finished_at' => Carbon::now()->toDateTimeString(),
                ];

                $this->writeLocal("{$relativeDir}/last_run.json", $this->encodeJson($runSummary, pretty: true));

                try {
                    DB::table('analytee_accounts')
                        ->where('team_id', $teamId)
                        ->where('account_id', $accountId)
                        ->update([
                            'status' => 'analysis_completed',
                            'updated' => time(),
                        ]);
                } catch (\Throwable) {
                }

                Log::info('Analytee engine run completed', [
                    'team_id' => $teamId,
                    'account_id' => $accountId,
                    'input_json' => $relativeInputPath,
                    'out_dir' => $outputInfo['expected_out_dir'] ?? null,
                ]);
            } catch (\Throwable $e) {
                try {
                    DB::table('analytee_accounts')->updateOrInsert(
                        ['team_id' => $teamId, 'account_id' => $accountId],
                        [
                            'place_id' => $placeId,
                            'status' => 'analysis_failed',
                            'updated' => time(),
                        ]
                    );
                } catch (\Throwable) {
                }

                try {
                    $runSummary = [
                        'team_id' => $teamId,
                        'account_id' => $accountId,
                        'place_id' => $placeId,
                        'input_json_relative_path' => $relativeInputPath,
                        'engine' => [
                            'mode' => 'prod',
                        ],
                        'error' => [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                        ],
                        'artifacts' => [
                            'engine_stdout' => "{$relativeDir}/engine_stdout.log",
                            'engine_stderr' => "{$relativeDir}/engine_stderr.log",
                        ],
                        'failed_at' => Carbon::now()->toDateTimeString(),
                    ];
                    $this->writeLocal("{$relativeDir}/last_run.json", $this->encodeJson($runSummary, pretty: true));
                } catch (\Throwable) {
                }

                throw $e;
            }
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function shouldUseFixtures(): bool
    {
        $flag = (string) (env('ANALYTEE_ENGINE_FIXTURE_MODE', '') ?? '');

        if (! ($flag === '1' || strtolower($flag) === 'true')) {
            return false;
        }

        return app()->runningUnitTests() || app()->environment('testing');
    }

    private function handleWithFixtures(): void
    {
        $teamId = (int) $this->teamId;
        $accountId = (int) $this->accountId;

        if ($teamId <= 0 || $accountId <= 0) {
            throw new \RuntimeException('team_id/account_id inválidos');
        }

        $account = $this->loadFixtureAccount($teamId, $accountId);
        if (! $account) {
            throw new \RuntimeException('analytee_accounts no encontrado para este team/account (fixture)');
        }

        $placeId = trim((string) ($account['place_id'] ?? ''));
        if ($placeId === '') {
            throw new \RuntimeException('place_id no disponible (abortado)');
        }

        $rawReviews = $this->loadFixtureReviews($teamId, $accountId, $placeId);
        $serializer = new GoldenJsonV1Serializer;
        $golden = $serializer->build($account, $rawReviews);
        $jsonString = $serializer->encode($golden);

        $relativeDir = "analytee/exports/{$teamId}/{$accountId}";
        $relativeInputPath = "{$relativeDir}/input.json";
        $this->writeLocal($relativeInputPath, $jsonString);
        $absoluteInputPath = $this->localAbsPath($relativeInputPath);

        $engineResult = $this->dryRunEngineOutputs($absoluteInputPath);
        $outputInfo = $this->verifyEngineOutputs($absoluteInputPath);
        $storedReports = $this->storeEngineOutputsToLocal($relativeDir, $outputInfo);

        $referenceInfo = $this->compareWithReferenceIfPresent($relativeDir, $golden);

        $runSummary = [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'place_id' => $placeId,
            'input_json_relative_path' => $relativeInputPath,
            'input_json_sha256' => hash('sha256', $jsonString),
            'reviews_count_input' => count($rawReviews),
            'reviews_count_output' => count((array) ($golden['reviews'] ?? [])),
            'engine' => $engineResult,
            'outputs' => $outputInfo,
            'stored_reports' => $storedReports,
            'reference_comparison' => $referenceInfo,
            'finished_at' => Carbon::now()->toDateTimeString(),
        ];

        $this->writeLocal("{$relativeDir}/last_run.json", $this->encodeJson($runSummary, pretty: true));

        Log::info('Analytee engine run completed (fixture)', [
            'team_id' => $teamId,
            'account_id' => $accountId,
            'input_json' => $relativeInputPath,
            'out_dir' => $outputInfo['expected_out_dir'] ?? null,
        ]);
    }

    private function loadFixtureAccount(int $teamId, int $accountId): ?array
    {
        $dir = (string) (env('ANALYTEE_ENGINE_FIXTURE_DIR', base_path('tests/Fixtures/AppAnalytee')) ?? '');
        $path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'analytee_accounts.json';

        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            throw new \RuntimeException('Fixture analytee_accounts.json no encontrado');
        }

        $rows = json_decode($raw, true);
        if (! is_array($rows)) {
            throw new \RuntimeException('Fixture analytee_accounts.json inválido');
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['team_id'] ?? 0) !== $teamId || (int) ($row['account_id'] ?? 0) !== $accountId) {
                continue;
            }

            return [
                'place_id' => $row['place_id'] ?? null,
                'rating' => $row['rating'] ?? null,
                'user_ratings_total' => $row['user_ratings_total'] ?? null,
                'website' => $row['website'] ?? null,
                'types' => $row['types'] ?? null,
                'vicinity' => $row['vicinity'] ?? null,
            ];
        }

        return null;
    }

    private function loadFixtureReviews(int $teamId, int $accountId, string $placeId): array
    {
        $dir = (string) (env('ANALYTEE_ENGINE_FIXTURE_DIR', base_path('tests/Fixtures/AppAnalytee')) ?? '');
        $path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'analytee_reviews.json';

        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            throw new \RuntimeException('Fixture analytee_reviews.json no encontrado');
        }

        $rows = json_decode($raw, true);
        if (! is_array($rows)) {
            throw new \RuntimeException('Fixture analytee_reviews.json inválido');
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['team_id'] ?? 0) !== $teamId || (int) ($row['account_id'] ?? 0) !== $accountId) {
                continue;
            }
            if ((string) ($row['place_id'] ?? '') !== $placeId) {
                continue;
            }

            $out[] = [
                'external_id' => $row['external_id'] ?? null,
                'author_name' => $row['author_name'] ?? null,
                'rating' => $row['rating'] ?? null,
                'text' => $row['text'] ?? null,
                'published_at' => $row['published_at'] ?? null,
                'owner_response_text' => $row['owner_response_text'] ?? null,
                'meta' => $row['meta'] ?? null,
            ];
        }

        return $out;
    }

    private function dryRunEngineOutputs(string $absoluteInputPath): array
    {
        $pipelineVersion = $this->readPipelineVersion();
        $clientSafe = pathinfo($absoluteInputPath, PATHINFO_FILENAME);
        $expectedOutDir = base_path('analytics_engine'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.$clientSafe.DIRECTORY_SEPARATOR.'v'.$pipelineVersion);

        if (! is_dir($expectedOutDir) && ! @mkdir($expectedOutDir, 0777, true) && ! is_dir($expectedOutDir)) {
            throw new \RuntimeException('No se pudo crear directorio de salida (fixture): '.$expectedOutDir);
        }

        $docxPath = $expectedOutDir.DIRECTORY_SEPARATOR.$clientSafe.'_informe_PROFESIONAL.docx';
        $pdfPath = $expectedOutDir.DIRECTORY_SEPARATOR.$clientSafe.'_informe_PROFESIONAL.pdf';
        $logPath = $expectedOutDir.DIRECTORY_SEPARATOR.'execution_log.json';

        file_put_contents($docxPath, 'FIXTURE_DOCX');
        file_put_contents($pdfPath, 'FIXTURE_PDF');

        $inputHash = $this->sha256File($absoluteInputPath);
        $docxHash = $this->sha256File($docxPath);
        $pdfHash = $this->sha256File($pdfPath);

        $payload = [
            'pipeline_version' => $pipelineVersion,
            'mode' => 'prod',
            'input_hash' => $inputHash,
            'docx_hash' => $docxHash,
            'pdf_hash' => $pdfHash,
        ];

        file_put_contents($logPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return [
            'mode' => 'fixture',
            'cwd' => base_path('analytics_engine'),
            'pipeline_version' => $pipelineVersion,
        ];
    }

    private function runEngine(string $absoluteInputPath): array
    {
        $python = trim((string) (env('ANALYTEE_PYTHON_BIN', 'python') ?: 'python'));
        $engineDir = base_path('analytics_engine');

        $process = new Process([$python, 'main_ai.py', '--mode', 'prod', '--input', $absoluteInputPath], $engineDir);
        $process->setTimeout((int) (env('ANALYTEE_ENGINE_TIMEOUT', 3600) ?: 3600));
        $process->run();

        $stdout = (string) $process->getOutput();
        $stderr = (string) $process->getErrorOutput();
        $exitCode = (int) $process->getExitCode();

        $relativeDir = "analytee/exports/{$this->teamId}/{$this->accountId}";
        $this->writeLocal("{$relativeDir}/engine_stdout.log", $stdout);
        $this->writeLocal("{$relativeDir}/engine_stderr.log", $stderr);

        if ($exitCode !== 0) {
            throw new \RuntimeException("analytics_engine falló (exit_code={$exitCode})");
        }

        return [
            'python_bin' => $python,
            'cwd' => $engineDir,
            'exit_code' => $exitCode,
        ];
    }

    private function encodeJson(array $payload, bool $pretty = false): string
    {
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($payload, $options);
        if (! is_string($json) || $json === '') {
            throw new \RuntimeException('JSON inválido (json_encode falló)');
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON inválido: '.json_last_error_msg());
        }

        return $json;
    }

    private function verifyEngineOutputs(string $absoluteInputPath): array
    {
        $pipelineVersion = $this->readPipelineVersion();
        $clientSafe = pathinfo($absoluteInputPath, PATHINFO_FILENAME);
        $expectedOutDir = base_path('analytics_engine'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.$clientSafe.DIRECTORY_SEPARATOR.'v'.$pipelineVersion);

        if (! is_dir($expectedOutDir)) {
            throw new \RuntimeException('Directorio de salida no existe: '.$expectedOutDir);
        }

        $docx = $this->globSingle($expectedOutDir, '*_informe_PROFESIONAL.docx');
        $pdf = $this->globSingle($expectedOutDir, '*_informe_PROFESIONAL.pdf');
        $logPath = $expectedOutDir.DIRECTORY_SEPARATOR.'execution_log.json';

        if (! is_file($logPath)) {
            throw new \RuntimeException('execution_log.json no existe');
        }

        $log = json_decode((string) file_get_contents($logPath), true);
        if (! is_array($log)) {
            throw new \RuntimeException('execution_log.json inválido');
        }

        $inputHash = $this->sha256File($absoluteInputPath);
        $docxHash = $this->sha256File($docx);
        $pdfHash = $this->sha256File($pdf);

        $logInput = (string) ($log['input_hash'] ?? '');
        $logDocx = (string) ($log['docx_hash'] ?? '');
        $logPdf = (string) ($log['pdf_hash'] ?? '');

        if ($logInput === '' || $logDocx === '' || $logPdf === '') {
            throw new \RuntimeException('execution_log.json sin hashes');
        }

        if (! hash_equals($logInput, $inputHash)) {
            throw new \RuntimeException('Hash input no coincide con execution_log');
        }

        if (! hash_equals($logDocx, $docxHash)) {
            throw new \RuntimeException('Hash DOCX no coincide con execution_log');
        }

        if (! hash_equals($logPdf, $pdfHash)) {
            throw new \RuntimeException('Hash PDF no coincide con execution_log');
        }

        return [
            'pipeline_version' => $pipelineVersion,
            'client_safe' => $clientSafe,
            'expected_out_dir' => $expectedOutDir,
            'docx_path' => $docx,
            'pdf_path' => $pdf,
            'execution_log_path' => $logPath,
            'input_sha256' => $inputHash,
            'docx_sha256' => $docxHash,
            'pdf_sha256' => $pdfHash,
        ];
    }

    private function readPipelineVersion(): string
    {
        if ((int) (env('ANALYTEE_ENGINE_FIXTURE_MODE', 0) ?: 0) === 1) {
            $fromEnv = trim((string) (env('ANALYTEE_PIPELINE_VERSION', '') ?: ''));
            if ($fromEnv !== '') {
                return $fromEnv;
            }

            return 'fixture';
        }

        $path = base_path('analytics_engine'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'report_generator_professional.py');
        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            throw new \RuntimeException('No se pudo leer PIPELINE_VERSION');
        }
        if (! preg_match('/^PIPELINE_VERSION\s*=\s*"([^"]+)"/m', $raw, $m)) {
            throw new \RuntimeException('No se pudo extraer PIPELINE_VERSION');
        }
        $v = trim((string) ($m[1] ?? ''));
        if ($v === '') {
            throw new \RuntimeException('PIPELINE_VERSION vacío');
        }

        return $v;
    }

    private function globSingle(string $dir, string $pattern): string
    {
        $paths = glob(rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$pattern) ?: [];
        $paths = array_values(array_filter($paths, fn ($p) => is_string($p) && $p !== '' && is_file($p)));
        if (count($paths) !== 1) {
            throw new \RuntimeException("Contrato de salida inválido: esperado 1 {$pattern}, encontrado ".count($paths));
        }

        return $paths[0];
    }

    private function storeEngineOutputsToLocal(string $relativeDir, array $outputInfo): array
    {
        $pipelineVersion = trim((string) ($outputInfo['pipeline_version'] ?? ''));
        if ($pipelineVersion === '') {
            $pipelineVersion = 'unknown';
        }

        $targetDir = "{$relativeDir}/reports/v{$pipelineVersion}";

        $expectedOutDir = (string) ($outputInfo['expected_out_dir'] ?? '');
        $analysisAbs = $expectedOutDir !== '' ? ($expectedOutDir.DIRECTORY_SEPARATOR.'analysis.json') : '';

        $toCopy = [
            'docx' => (string) ($outputInfo['docx_path'] ?? ''),
            'pdf' => (string) ($outputInfo['pdf_path'] ?? ''),
            'execution_log' => (string) ($outputInfo['execution_log_path'] ?? ''),
        ];

        if ($analysisAbs !== '' && is_file($analysisAbs)) {
            $toCopy['analysis'] = $analysisAbs;
        }

        $files = [];
        foreach ($toCopy as $key => $absPath) {
            $absPath = trim((string) $absPath);
            if ($absPath === '') {
                continue;
            }
            $raw = @file_get_contents($absPath);
            if (! is_string($raw)) {
                throw new \RuntimeException("No se pudo leer output del motor: {$absPath}");
            }

            $basename = basename($absPath);
            $rel = "{$targetDir}/{$basename}";
            $this->writeLocal($rel, $raw);
            $files[$key] = $rel;
        }

        return [
            'dir' => $targetDir,
            'files' => $files,
        ];
    }

    private function sha256File(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if (! is_string($hash) || $hash === '') {
            throw new \RuntimeException('No se pudo calcular sha256: '.$path);
        }

        return $hash;
    }

    private function compareWithReferenceIfPresent(string $relativeDir, array $golden): ?array
    {
        $refPath = "{$relativeDir}/reference.json";
        if (! $this->existsLocal($refPath)) {
            return null;
        }

        $raw = $this->readLocal($refPath);
        $ref = json_decode((string) $raw, true);
        if (! is_array($ref) || ! isset($ref['reviews']) || ! is_array($ref['reviews'])) {
            throw new \RuntimeException('reference.json inválido');
        }

        $generatedMetrics = $this->computeBasicMetrics($golden);
        $referenceMetrics = $this->computeBasicMetrics($ref);

        return [
            'reference_path' => $refPath,
            'generated' => $generatedMetrics,
            'reference' => $referenceMetrics,
            'same_reviews_count' => $generatedMetrics['reviews_count'] === $referenceMetrics['reviews_count'],
        ];
    }

    private function localAbsPath(string $relativePath): string
    {
        return storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    }

    private function writeLocal(string $relativePath, string $contents): void
    {
        $abs = $this->localAbsPath($relativePath);
        $dir = dirname($abs);
        if (! is_dir($dir) && ! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear directorio: '.$dir);
        }

        if (@file_put_contents($abs, $contents) === false) {
            throw new \RuntimeException('No se pudo escribir archivo: '.$abs);
        }
    }

    private function existsLocal(string $relativePath): bool
    {
        return is_file($this->localAbsPath($relativePath));
    }

    private function readLocal(string $relativePath): string
    {
        $abs = $this->localAbsPath($relativePath);
        $raw = @file_get_contents($abs);
        if (! is_string($raw)) {
            throw new \RuntimeException('No se pudo leer archivo: '.$abs);
        }

        return $raw;
    }

    private function computeBasicMetrics(array $payload): array
    {
        $reviews = (array) ($payload['reviews'] ?? []);
        $count = count($reviews);
        $sum = 0;
        $withResponse = 0;
        foreach ($reviews as $r) {
            if (! is_array($r)) {
                continue;
            }
            $sum += (int) ($r['rating'] ?? 0);
            $resp = $r['responseFromOwnerText'] ?? null;
            if (is_string($resp) && trim($resp) !== '') {
                $withResponse++;
            }
        }

        return [
            'reviews_count' => $count,
            'avg_rating' => $count > 0 ? round($sum / $count, 4) : 0.0,
            'response_rate_pct' => $count > 0 ? round($withResponse / $count * 100, 2) : 0.0,
        ];
    }
}
