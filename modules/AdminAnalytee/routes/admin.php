<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminAnalytee\Http\Controllers\AdminAnalyteePromptController;

Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    Route::get('analytee/prompts', [AdminAnalyteePromptController::class, 'index'])
        ->name('admin.analytee.prompts.index');

    Route::get('analytee/prompts/{prompt}/edit', [AdminAnalyteePromptController::class, 'edit'])
        ->name('admin.analytee.prompts.edit');

    Route::put('analytee/prompts/{prompt}', [AdminAnalyteePromptController::class, 'update'])
        ->name('admin.analytee.prompts.update');

    Route::put('analytee/prompts/{prompt}/publish', [AdminAnalyteePromptController::class, 'publish'])
        ->name('admin.analytee.prompts.publish');

    Route::delete('analytee/prompts/{prompt}', [AdminAnalyteePromptController::class, 'destroy'])
        ->name('admin.analytee.prompts.destroy');
});
