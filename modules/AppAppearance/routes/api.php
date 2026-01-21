<?php

use Illuminate\Support\Facades\Route;
use Modules\AppAppearance\Http\Controllers\AppAppearanceController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('appappearance', AppAppearanceController::class)->names('appappearance');
});
