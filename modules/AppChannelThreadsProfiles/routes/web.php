<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelThreadsProfiles\Http\Controllers\AppChannelThreadsProfilesController;

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
        Route::group(['prefix' => 'threads'], function () {
            Route::group(['prefix' => 'profile'], function () {
                Route::resource('/', AppChannelThreadsProfilesController::class)->names('app.channelthreadsprofiles');
                Route::get('oauth', [AppChannelThreadsProfilesController::class, 'oauth'])->name('app.channelthreadsprofiles.oauth');
            });
        });
    });

    Route::group(['prefix' => 'admin/api-integration'], function () {
        Route::get('threads', [AppChannelThreadsProfilesController::class, 'settings'])->name('app.channelthreadsprofiles.settings');
    });
});
