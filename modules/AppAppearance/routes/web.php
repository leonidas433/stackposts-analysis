<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAppearance\Livewire\Appearance;

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

Route::middleware('theme:pico')->group(function () {
    Route::group(['prefix' => 'app'], function () {
        Route::get('appearance', Appearance::class)->name('appappearance');
    });
});
