<?php

use Illuminate\Support\Facades\Route;
use Modules\AppChannelPinterestBoards\Http\Controllers\AppChannelPinterestBoardsController;

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
        Route::group(['prefix' => 'pinterest'], function () {
            Route::group(['prefix' => 'board'], function () {
                Route::resource('/', AppChannelPinterestBoardsController::class)->names('app.channelpinterestboards');
                Route::get('oauth', [AppChannelPinterestBoardsController::class, 'oauth'])->name('app.channelpinterestboards.oauth');
            });
        });
    });

    Route::group(['prefix' => 'admin/api-integration'], function () {
        Route::get('pinterest', [AppChannelPinterestBoardsController::class, 'settings'])->name('app.channelpinterestboards.reddit');
    });
});
