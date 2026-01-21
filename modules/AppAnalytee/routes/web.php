<?php

use App\Http\Controllers\AppAnalytee\ReviewAiPreviewController;
use Illuminate\Support\Facades\Route;
use Modules\AppAnalytee\Http\Controllers\AppAnalyteeController;
use Modules\AppAnalytee\Http\Controllers\AppAnalyteeReviewsController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::group(['prefix' => 'app'], function () {
        Route::get('analytee', [AppAnalyteeController::class, 'index'])->name('app.analytee.index');
        Route::post('analytee/link/{profile_id}', [AppAnalyteeController::class, 'link'])->name('app.analytee.link');
        Route::get('analytee/reviews/{account_id}', [AppAnalyteeReviewsController::class, 'index'])->name('app.analytee.reviews.index');
        Route::get('analytee/reviews/{account_id}/stats', [AppAnalyteeReviewsController::class, 'stats'])->name('app.analytee.reviews.stats');
        Route::get('analytee/reviews/{account_id}/report/{kind}', [AppAnalyteeReviewsController::class, 'report'])->name('app.analytee.reviews.report');
        Route::post('analytee/reviews/{account_id}/sync', [AppAnalyteeReviewsController::class, 'sync'])->name('app.analytee.reviews.sync');
        Route::post('analytee/reviews/{account_id}/prepare-input', [AppAnalyteeReviewsController::class, 'prepareInput'])->name('app.analytee.reviews.prepare-input');
        Route::post('analytee/reviews/{account_id}/run-engine', [AppAnalyteeReviewsController::class, 'runEngine'])->name('app.analytee.reviews.run-engine');
        Route::post('analytee/reviews/{account_id}/{external_id}/reply', [AppAnalyteeReviewsController::class, 'reply'])->name('app.analytee.reviews.reply');
        Route::get('analytee/reviews/ai-reply/available', [AppAnalyteeReviewsController::class, 'aiReplyAvailable'])->name('app.analytee.reviews.ai-reply.available');
        Route::post('analytee/reviews/{id}/ai-reply', [AppAnalyteeReviewsController::class, 'aiReply'])->name('app.analytee.reviews.ai-reply');
        Route::post('analytee/reviews/{review_id}/ai-preview', [ReviewAiPreviewController::class, 'preview'])->name('app.analytee.reviews.ai-preview');
    });
});
