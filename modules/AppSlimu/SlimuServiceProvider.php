<?php

namespace Modules\AppSlimu;

use Illuminate\Support\ServiceProvider;

class SlimuServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Dynamically add Slimu to the URL shortener platforms
        app('url_shortener')->extend('slimu', function () {
            return new SlimuService;
        });
    }

    public function boot()
    {
        // Add Slimu settings dynamically to the admin panel
        add_filter('admin_url_shorteners_settings', function ($settings) {
            \Log::info('Slimu settings hook executed');
            $settings['slimu'] = [
                'name' => __('Slimu.in'),
                'fields' => [
                    'slimu_status' => [
                        'type' => 'radio',
                        'label' => __('Status'),
                        'options' => [
                            1 => __('Enable'),
                            0 => __('Disable'),
                        ],
                    ],
                    'slimu_api_key' => [
                        'type' => 'text',
                        'label' => __('API Key'),
                    ],
                ],
            ];

            return $settings;
        });

        add_filter('url_shortener_platforms', function ($platforms) {
            $platforms['slimu'] = __('Slimu.in');

            return $platforms;
        });
    }
}
