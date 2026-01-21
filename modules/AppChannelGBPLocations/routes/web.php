<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelGBPLocations\Http\Controllers\AppChannelGBPLocationsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['web', 'auth'])->group(function () {
    Route::group(['prefix' => 'app'], function () {
        Route::group(['prefix' => 'google_business_profile'], function () {
            Route::group(['prefix' => 'location'], function () {
                Route::resource('/', AppChannelGBPLocationsController::class)->names('app.channelgbplocations');
                Route::get('oauth', [AppChannelGBPLocationsController::class, 'oauth'])->name('app.channelgbplocations.oauth');
            });
        });
    });

    Route::group(['prefix' => 'admin/api-integration'], function () {
        Route::get('google_business_profile', [AppChannelGBPLocationsController::class, 'settings'])->name('app.channelgbplocations.settings');
    });
});
