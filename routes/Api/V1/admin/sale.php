<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Enrollment\ChangeEnrollmentStatusController;
use App\Http\Controllers\Api\Admin\Enrollment\EnrollmentController;
use App\Http\Controllers\Api\Admin\Enrollment\RetryProvisioningController;
use App\Http\Controllers\Api\Admin\Order\ApproveOrderController;
use App\Http\Controllers\Api\Admin\Order\NextPaymentDetailsController;
use App\Http\Controllers\Api\Admin\Order\OrderCalculationController;
use App\Http\Controllers\Api\Admin\Order\OrderController;
use App\Http\Controllers\Api\Admin\Order\OrderItemController;
use App\Http\Controllers\Api\Admin\Order\PaymentController;
use App\Http\Controllers\Api\Admin\Order\RefundUpdateStatusController;
use App\Http\Controllers\Api\Admin\Payment\DigipayAdminController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountInfoController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountPromotionController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountPromotionStatisticsController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountPromotionStatusUpdateController;
use App\Http\Controllers\Api\Admin\Wallet\AdjustWalletController;
use App\Http\Controllers\Api\Admin\Wallet\AdminWalletController;
use App\Http\Controllers\Api\Admin\Wallet\CreateWalletController;
use App\Http\Controllers\Api\Admin\Wallet\DepositToWalletController;
use App\Http\Controllers\Api\Admin\Wallet\WithdrawFromWalletController;

// Order and Payment Management
Route::apiResource('order', OrderController::class);
Route::post('order/preview', OrderCalculationController::class)
    ->name('order.preview');
Route::post('order/{order}/approve', ApproveOrderController::class)
    ->name('order.approve');
Route::apiResource('order/{order}/order-item', OrderItemController::class)
    ->only(['index', 'show']);

Route::apiResource('order/{order}/payment', PaymentController::class);
Route::get('order/{order}/next-payment-details', NextPaymentDetailsController::class)
    ->name('next-payment-details');

// Digipay admin operations
Route::prefix('payment/{payment}/digipay')->name('payment.digipay.')->group(function (): void {
    Route::post('refund', [DigipayAdminController::class, 'refund'])->name('refund');
    Route::post('deliver', [DigipayAdminController::class, 'deliver'])->name('deliver');
    Route::post('reverse', [DigipayAdminController::class, 'reverse'])->name('reverse');
});
Route::post('payment/digipay/inquire-refund', [DigipayAdminController::class, 'inquireRefund'])
    ->name('payment.digipay.inquire-refund');

Route::apiResource('/order-item/{orderItem}/refund', App\Http\Controllers\Api\Admin\Order\RefundController::class);
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

// Enrollment Management
Route::apiResource('enrollment', EnrollmentController::class)->except(['store']);
Route::post('enrollment/{enrollment}/change-status', ChangeEnrollmentStatusController::class)
    ->name('enrollment.change-status');
Route::post('enrollment/{enrollment}/retry-provisioning', RetryProvisioningController::class)
    ->name('enrollment.retry-provisioning');

Route::prefix('wallet')->name('wallet.')->group(function (): void {
    Route::apiResource('/', AdminWalletController::class)->only(['index', 'show'])->parameters(['' => 'wallet']);

    Route::post('create', CreateWalletController::class)->name('create');
    Route::post('deposit/{wallet}', DepositToWalletController::class)->name('deposit');
    Route::post('withdrawal/{wallet}', WithdrawFromWalletController::class)->name('withdrawal');
    Route::post('adjustment/{wallet}', AdjustWalletController::class)->name('adjustment');
});
