<?php

namespace Tests\Unit;

use Modules\AppAnalytee\Jobs\RunAnalyticsEngineGoldenJsonJob;
use Modules\AppAnalytee\Services\GoldenJsonV1Serializer;
use Tests\TestCase;

class RunAnalyticsEngineGoldenJsonJobTest extends TestCase
{
    public function test_fixture_mode_generates_golden_json_and_validates_contract(): void
    {
        putenv('ANALYTEE_ENGINE_FIXTURE_MODE=1');
        putenv('ANALYTEE_ENGINE_FIXTURE_DIR='.base_path('tests/Fixtures/AppAnalytee'));

        $teamId = 1;
        $accountId = 101;

        (new RunAnalyticsEngineGoldenJsonJob($teamId, $accountId))->handle();

        $relativeDir = "analytee/exports/{$teamId}/{$accountId}";
        $inputRel = "{$relativeDir}/input.json";
        $lastRunRel = "{$relativeDir}/last_run.json";

        $inputAbs = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $inputRel));
        $lastRunAbs = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $lastRunRel));

        $this->assertTrue(is_file($inputAbs));
        $this->assertTrue(is_file($lastRunAbs));

        $jsonString = (string) file_get_contents($inputAbs);
        $this->assertLessThan(GoldenJsonV1Serializer::MAX_JSON_BYTES, strlen($jsonString));

        $payload = json_decode((string) $jsonString, true);
        $this->assertIsArray($payload);

        (new GoldenJsonV1Serializer)->validate($payload);

        $lastRun = json_decode((string) file_get_contents($lastRunAbs), true);
        $this->assertIsArray($lastRun);
        $this->assertSame('fixture', $lastRun['engine']['mode'] ?? null);

        $stored = $lastRun['stored_reports'] ?? null;
        $this->assertIsArray($stored);
        $storedFiles = $stored['files'] ?? null;
        $this->assertIsArray($storedFiles);

        $expectedOutDir = (string) ($lastRun['outputs']['expected_out_dir'] ?? '');
        $this->assertNotSame('', $expectedOutDir);

        $docxPath = (string) ($lastRun['outputs']['docx_path'] ?? '');
        $pdfPath = (string) ($lastRun['outputs']['pdf_path'] ?? '');
        $logPath = (string) ($lastRun['outputs']['execution_log_path'] ?? '');

        $this->assertTrue(is_file($docxPath));
        $this->assertTrue(is_file($pdfPath));
        $this->assertTrue(is_file($logPath));

        $log = json_decode((string) file_get_contents($logPath), true);
        $this->assertIsArray($log);
        $this->assertSame(hash_file('sha256', $inputAbs), $log['input_hash'] ?? null);

        $storedDocxRel = (string) ($storedFiles['docx'] ?? '');
        $storedPdfRel = (string) ($storedFiles['pdf'] ?? '');
        $storedLogRel = (string) ($storedFiles['execution_log'] ?? '');

        $this->assertNotSame('', $storedDocxRel);
        $this->assertNotSame('', $storedPdfRel);
        $this->assertNotSame('', $storedLogRel);

        $storedDocxAbs = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $storedDocxRel));
        $storedPdfAbs = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $storedPdfRel));
        $storedLogAbs = storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $storedLogRel));

        $this->assertTrue(is_file($storedDocxAbs));
        $this->assertTrue(is_file($storedPdfAbs));
        $this->assertTrue(is_file($storedLogAbs));

        $this->assertSame('FIXTURE_DOCX', (string) file_get_contents($storedDocxAbs));
        $this->assertSame('FIXTURE_PDF', (string) file_get_contents($storedPdfAbs));

        $storedLog = json_decode((string) file_get_contents($storedLogAbs), true);
        $this->assertIsArray($storedLog);
        $this->assertSame(hash_file('sha256', $inputAbs), $storedLog['input_hash'] ?? null);
    }
}
