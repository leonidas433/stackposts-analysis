<?php

namespace Modules\AppAnalytee\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\AppAnalytee\Jobs\SyncGbpReviewsJob;
use Modules\AppChannels\Models\Accounts;

class AppAnalyteeServiceProvider extends ServiceProvider
{
    protected string $name = 'AppAnalytee';

    protected string $nameLower = 'appanalytee';

    public function boot(): void
    {
        $this->loadViewsFrom(module_path($this->name, 'resources/views'), $this->nameLower);
        $this->loadRoutesFrom(module_path($this->name, 'routes/web.php'));

        $this->registerCommandSchedules();

        if (! Gate::has($this->nameLower)) {
            Gate::define($this->nameLower, fn () => true);
        }

        // \AppDashboard::registerDashboardItem(function () {
        //     return view('appanalytee::partials.dashboard-item')->render();
        // }, 16000, fn () => 1);
    }

    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

            $schedule->call(function () {
                $accounts = Accounts::query()
                    ->where('social_network', 'google_business_profile')
                    ->where('category', 'location')
                    ->where('status', 1)
                    ->get(['id', 'team_id']);

                foreach ($accounts as $account) {
                    SyncGbpReviewsJob::dispatch((int) $account->team_id, (int) $account->id);
                }
            })->dailyAt('02:00');

            $schedule->call(function () {
                $accounts = Accounts::query()
                    ->where('social_network', 'google_business_profile')
                    ->where('category', 'location')
                    ->where('status', 2)
                    ->get(['id', 'team_id']);

                foreach ($accounts as $account) {
                    SyncGbpReviewsJob::dispatch((int) $account->team_id, (int) $account->id);
                }
            })->weeklyOn(1, '02:00');
        });
    }
}
