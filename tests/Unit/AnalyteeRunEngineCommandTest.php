<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Modules\AppAnalytee\Jobs\RunAnalyticsEngineGoldenJsonJob;
use Tests\TestCase;

class AnalyteeRunEngineCommandTest extends TestCase
{
    public function test_command_dispatches_job_with_fixtures(): void
    {
        putenv('ANALYTEE_ENGINE_FIXTURE_MODE=1');
        putenv('ANALYTEE_ENGINE_FIXTURE_DIR='.base_path('tests/Fixtures/AppAnalytee'));

        Bus::fake();

        $exitCode = Artisan::call('analytee:run-engine', [
            'team_id' => 1,
            'account_id' => 101,
        ]);

        $this->assertSame(0, $exitCode);

        Bus::assertDispatched(RunAnalyticsEngineGoldenJsonJob::class, function (RunAnalyticsEngineGoldenJsonJob $job): bool {
            return $job->teamId === 1 && $job->accountId === 101;
        });
    }

    public function test_command_blocks_when_lock_exists(): void
    {
        putenv('ANALYTEE_ENGINE_FIXTURE_MODE=1');
        putenv('ANALYTEE_ENGINE_FIXTURE_DIR='.base_path('tests/Fixtures/AppAnalytee'));

        Bus::fake();

        $lockKey = 'analytee:engine:run:1:101';
        Cache::put($lockKey, 'locked', 60);

        $exitCode = Artisan::call('analytee:run-engine', [
            'team_id' => 1,
            'account_id' => 101,
        ]);

        $this->assertSame(1, $exitCode);
        Bus::assertNotDispatched(RunAnalyticsEngineGoldenJsonJob::class);

        Cache::forget($lockKey);
    }
}
