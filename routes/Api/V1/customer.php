<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Shop\Sale\CancelOrderController;
use App\Http\Controllers\Api\Shop\Sale\OrderController;
use App\Http\Controllers\Api\Shop\Sale\RetryPaymentController;

Route::middleware(['auth:user'])
    ->prefix('shop')
    ->name('shop.')
    ->group(function (): void {
        Route::singleton('profile', App\Http\Controllers\Api\Shop\ProfileController::class)
            ->only(['show', 'update']);

        Route::prefix('my-courses')->name('my-courses.')->group(function (): void {
            Route::get('/', [App\Http\Controllers\Api\Shop\MyCourses\EnrollmentController::class, 'index'])
                ->name('index');

            Route::get('/{enrollment:uuid}',
                [App\Http\Controllers\Api\Shop\MyCourses\EnrollmentController::class, 'show'])
                ->name('show');

            Route::post('/{enrollment:uuid}/moodle/sso',
                App\Http\Controllers\Api\Shop\MyCourses\MoodleSsoController::class)
                ->name('moodle.sso');
        });

        Route::get('/my-digital-assets', App\Http\Controllers\Api\Shop\MyCourses\DigitalAssetEnrollmentController::class)
            ->name('my-digital-assets.index');

        Route::get('/my-digital-assets/{enrollment:uuid}/download/{digitalAsset}',
            App\Http\Controllers\Api\Shop\MyCourses\DigitalAssetDownloadController::class)
            ->name('my-digital-assets.download');

        Route::prefix('orders')->name('orders.')->group(function (): void {
            Route::get('/', [OrderController::class, 'index'])
                ->name('index');

            Route::get('/{order:increment_id}', [OrderController::class, 'show'])
                ->name('show');

            Route::post('/{order:increment_id}/cancel', CancelOrderController::class)
                ->name('cancel');

            Route::post('/{order:increment_id}/retry-payment', RetryPaymentController::class)
                ->middleware('throttle:10,1')
                ->name('retry-payment');
        });
    });
