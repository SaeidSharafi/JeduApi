<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\DiscountInfoController;
use App\Http\Controllers\Api\Admin\DiscountPromotionController;
use App\Http\Controllers\Api\Admin\DiscountPromotionStatisticsController;
use App\Http\Controllers\Api\Admin\DiscountPromotionStatusUpdateController;
use App\Http\Controllers\Api\Admin\NextPaymentDetailsController;
use App\Http\Controllers\Api\Admin\OrderCalculationController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\OrderItemController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\RefundUpdateStatusController;
use App\Http\Controllers\Api\Admin\Wallet\AdjustWalletController;
use App\Http\Controllers\Api\Admin\Wallet\AdminWalletController;
use App\Http\Controllers\Api\Admin\Wallet\CreateWalletController;
use App\Http\Controllers\Api\Admin\Wallet\DepositToWalletController;
use App\Http\Controllers\Api\Admin\Wallet\WithdrawFromWalletController;

// Order and Payment Management
Route::apiResource('order', OrderController::class);
Route::post('order/preview', OrderCalculationController::class)
    ->name('order.preview');
Route::apiResource('order/{order}/order-item', OrderItemController::class)
    ->only(['index', 'show']);

Route::apiResource('order/{order}/payment', PaymentController::class);
Route::get('order/{order}/next-payment-details', NextPaymentDetailsController::class)
    ->name('next-payment-details');

Route::apiResource('/order-item/{orderItem}/refund', App\Http\Controllers\Api\Admin\RefundController::class);
Route::put('refund/{refund}/status', RefundUpdateStatusController::class)
    ->name('refund.status');

// Discount Promotion routes
Route::apiResource('discount-promotion', DiscountPromotionController::class);
Route::put('discount-promotion/{discountPromotion}/status', DiscountPromotionStatusUpdateController::class)
    ->name('discount-promotion.toggle-status');
Route::get('discount-promotion-statistics', DiscountPromotionStatisticsController::class)
    ->name('discount-promotion.statistics');

// Discount Info routes (for frontend to get available rules, actions, etc.)
Route::get('discount-info', [DiscountInfoController::class, 'index'])
    ->name('discount-info');
Route::get('discount-info/conditions',
    [DiscountInfoController::class, 'conditions'])
    ->name('discount-info.conditions');
Route::get('discount-info/actions', [DiscountInfoController::class, 'actions'])
    ->name('discount-info.actions');
Route::get('discount-info/operators', [DiscountInfoController::class, 'operators'])
    ->name('discount-info.operators');
Route::get('discount-info/types', [DiscountInfoController::class, 'types'])
    ->name('discount-info.types');
Route::prefix('wallet')->name('wallet.')->group(function (): void {
    Route::apiResource('/', AdminWalletController::class)->only(['index', 'show'])->parameters(['' => 'wallet']);

    Route::post('create', CreateWalletController::class)->name('create');
    Route::post('deposit/{wallet}', DepositToWalletController::class)->name('deposit');
    Route::post('withdrawal/{wallet}', WithdrawFromWalletController::class)->name('withdrawal');
    Route::post('adjustment/{wallet}', AdjustWalletController::class)->name('adjustment');
});
