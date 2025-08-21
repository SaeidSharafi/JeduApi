<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\DiscountPromotionController;
use App\Http\Controllers\Api\Admin\DiscountPromotionStatisticsController;
use App\Http\Controllers\Api\Admin\DiscountPromotionStatusUpdateController;
use App\Http\Controllers\Api\Admin\FileManagement\UploadMediaController;
use App\Http\Controllers\Api\Admin\FileManagement\UploadPrivateController;
use App\Http\Controllers\Api\Admin\FileManagement\ViewMediaController;
use App\Http\Controllers\Api\Admin\FileManagement\ViewPrivateFileController;
use App\Http\Controllers\Api\Admin\NextPaymentDetailsController;
use App\Http\Controllers\Api\Admin\OrderCalculationController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PrivateFileDownloadController;

Route::middleware('auth:staff')->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('staff', App\Http\Controllers\Api\Admin\StaffController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::resource('role', App\Http\Controllers\Api\Admin\RoleController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::get('permission', App\Http\Controllers\Api\Admin\PermissonController::class)
            ->name('permission.index');
        Route::resource('vendor', App\Http\Controllers\Api\Admin\VendorController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::resource('teacher', App\Http\Controllers\Api\Admin\TeacherController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::post('media/upload', UploadMediaController::class)->name('media.upload');
        Route::get('media/{media}', ViewMediaController::class)->name('media.view');
        Route::post('private-file/upload', UploadPrivateController::class)
            ->name('private-upload.upload');
        Route::get('private-file/{file}', ViewPrivateFileController::class)
            ->name('private-upload.view');
        Route::get('private-file/{file}/download', PrivateFileDownloadController::class)
            ->name('private-upload.download');

        Route::resource('term', App\Http\Controllers\Api\Admin\TermController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::resource('user', App\Http\Controllers\Api\Admin\UserController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::resource('order', OrderController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::post('order/preview', OrderCalculationController::class)
        ->name('order.preview');

        Route::resource('order/{order}/order-item', App\Http\Controllers\Api\Admin\OrderItemController::class)
            ->only(['index', 'show']);

        Route::resource('order/{order}/payment', App\Http\Controllers\Api\Admin\PaymentController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::get('order/{order}/next-payment-details', NextPaymentDetailsController::class)
            ->name('next-payment-details');

        Route::resource('/order-item/{orderItem}/refund', App\Http\Controllers\Api\Admin\RefundController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::put('refund/{refund}/status', App\Http\Controllers\Api\Admin\RefundUpdateStatusController::class)
            ->name('refund.status');

        // Discount Promotion routes
        Route::resource('discount-promotion', DiscountPromotionController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::put('discount-promotion/{discountPromotion}/status', DiscountPromotionStatusUpdateController::class)
            ->name('discount-promotion.toggle-status');
        Route::get('discount-promotion-statistics', DiscountPromotionStatisticsController::class)
            ->name('discount-promotion.statistics');

        // Discount Info routes (for frontend to get available rules, actions, etc.)
        Route::get('discount-info', [\App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'index'])
            ->name('discount-info');
        Route::get('discount-info/conditions', [\App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'conditions'])
            ->name('discount-info.conditions');
        Route::get('discount-info/actions', [\App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'actions'])
            ->name('discount-info.actions');
        Route::get('discount-info/operators', [\App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'operators'])
            ->name('discount-info.operators');
        Route::get('discount-info/types', [\App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'types'])
            ->name('discount-info.types');

    });
});
