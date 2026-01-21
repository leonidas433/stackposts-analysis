<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\AppAnalytee\Jobs\RunAnalyticsEngineGoldenJsonJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('analytee:run-engine {team_id} {account_id}', function () {
    $teamId = (int) $this->argument('team_id');
    $accountId = (int) $this->argument('account_id');

    $shouldUseFixtures = function (): bool {
        $flag = (string) (env('ANALYTEE_ENGINE_FIXTURE_MODE', '') ?? '');

        if (! ($flag === '1' || strtolower($flag) === 'true')) {
            return false;
        }

        return app()->runningUnitTests() || app()->environment('testing');
    };

    $readJsonFile = function (string $path): array {
        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            throw new \RuntimeException('No se pudo leer fixture: '.$path);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Fixture inválido: '.$path);
        }

        return $decoded;
    };

    $loadFixtureAccount = function (int $teamId, int $accountId) use ($readJsonFile): ?array {
        $dir = (string) (env('ANALYTEE_ENGINE_FIXTURE_DIR', base_path('tests/Fixtures/AppAnalytee')) ?? '');
        $path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'analytee_accounts.json';

        $rows = $readJsonFile($path);
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ((int) ($row['team_id'] ?? 0) !== $teamId || (int) ($row['account_id'] ?? 0) !== $accountId) {
                continue;
            }

            return $row;
        }

        return null;
    };

    $extractMetaSource = function (mixed $meta): ?string {
        if (is_array($meta)) {
            return isset($meta['source']) ? (string) $meta['source'] : null;
        }

        if (is_object($meta)) {
            return isset($meta->source) ? (string) $meta->source : null;
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (is_array($decoded) && array_key_exists('source', $decoded)) {
                return is_string($decoded['source']) ? $decoded['source'] : (is_null($decoded['source']) ? null : (string) $decoded['source']);
            }
        }

        return null;
    };

    $countValidFixtureReviews = function (int $teamId, int $accountId, string $placeId) use ($readJsonFile, $extractMetaSource): int {
        $dir = (string) (env('ANALYTEE_ENGINE_FIXTURE_DIR', base_path('tests/Fixtures/AppAnalytee')) ?? '');
        $path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'analytee_reviews.json';

        $rows = $readJsonFile($path);
        $count = 0;
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

            $rating = (int) ($row['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) {
                continue;
            }

            $publishedAt = $row['published_at'] ?? null;
            if ($publishedAt === null || trim((string) $publishedAt) === '') {
                continue;
            }

            $externalId = (string) ($row['external_id'] ?? '');
            if ($externalId !== '' && preg_match('/^[0-9a-f]{64}$/i', $externalId) === 1) {
                continue;
            }

            $source = $extractMetaSource($row['meta'] ?? null);
            if ($source !== 'gbp') {
                continue;
            }

            $count++;
        }

        return $count;
    };

    $isLockFree = function (string $key): bool {
        return ! Cache::has($key);
    };

    if ($teamId <= 0 || $accountId <= 0) {
        $this->error('team_id/account_id inválidos');

        return 1;
    }

    $timeout = (int) (env('ANALYTEE_ENGINE_TIMEOUT', 3600) ?: 3600);
    $ttlSeconds = max(60, $timeout + 300);
    $lockKey = "analytee:engine:run:{$teamId}:{$accountId}";

    if (! $isLockFree($lockKey)) {
        $this->error('Ejecución concurrente activa (lock)');

        return 1;
    }

    if ($shouldUseFixtures()) {
        $account = $loadFixtureAccount($teamId, $accountId);
        if (! is_array($account)) {
            $this->error('Cuenta no encontrada (fixture)');

            return 1;
        }

        $placeId = trim((string) ($account['place_id'] ?? ''));
        if ($placeId === '') {
            $this->error('place_id no disponible (abortado)');

            return 1;
        }

        $validCount = $countValidFixtureReviews($teamId, $accountId, $placeId);
        if ($validCount <= 0) {
            $this->error('No hay reseñas válidas (fixture)');

            return 1;
        }
    } else {
        $account = DB::table('analytee_accounts')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->first(['place_id']);

        if (! $account) {
            $this->error('Cuenta no encontrada');

            return 1;
        }

        $placeId = trim((string) ($account->place_id ?? ''));
        if ($placeId === '') {
            $this->error('place_id no disponible (abortado)');

            return 1;
        }

        $validCount = (int) DB::table('analytee_reviews')
            ->where('team_id', $teamId)
            ->where('account_id', $accountId)
            ->where('place_id', $placeId)
            ->whereBetween('rating', [1, 5])
            ->whereNotNull('published_at')
            ->where('published_at', '!=', '')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')) = 'gbp'")
            ->where('external_id', 'not regexp', '^[0-9a-f]{64}$')
            ->count();

        if ($validCount <= 0) {
            $this->error('No hay reseñas válidas');

            return 1;
        }
    }

    RunAnalyticsEngineGoldenJsonJob::dispatch($teamId, $accountId);
    $this->info("Job despachado (team_id={$teamId}, account_id={$accountId})");

    return 0;
})->purpose('Ejecuta el motor analytee vía Job (golden JSON)');
