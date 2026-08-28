<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Enrollment\AdvancedProvisioningDiagnosticsController;
use App\Http\Controllers\Api\Admin\Enrollment\ChangeEnrollmentStatusController;
use App\Http\Controllers\Api\Admin\Enrollment\EnrollmentController;
use App\Http\Controllers\Api\Admin\Enrollment\ProvisioningDiagnosticsController;
use App\Http\Controllers\Api\Admin\Enrollment\ProvisioningPlanController;
use App\Http\Controllers\Api\Admin\Enrollment\ResolveProvisioningController;
use App\Http\Controllers\Api\Admin\Enrollment\RetryProvisioningController;
use App\Http\Controllers\Api\Admin\Enrollment\WaiveProvisioningController;
use App\Http\Controllers\Api\Admin\Order\ApproveOrderController;
use App\Http\Controllers\Api\Admin\Order\NextPaymentDetailsController;
use App\Http\Controllers\Api\Admin\Order\OrderCalculationController;
use App\Http\Controllers\Api\Admin\Order\OrderController;
use App\Http\Controllers\Api\Admin\Order\OrderItemController;
use App\Http\Controllers\Api\Admin\Order\OrderRefundController;
use App\Http\Controllers\Api\Admin\Order\PaymentController;
use App\Http\Controllers\Api\Admin\Order\RefundController;
use App\Http\Controllers\Api\Admin\Order\RefundUpdateStatusController;
use App\Http\Controllers\Api\Admin\Payment\DigipayAdminController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountInfoController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountPromotionController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountPromotionStatisticsController;
use App\Http\Controllers\Api\Admin\Promotion\DiscountPromotionStatusUpdateController;

// Order and Payment Management
Route::apiResource('orders', OrderController::class);
Route::post('orders/preview', OrderCalculationController::class)
    ->name('orders.preview');
Route::post('orders/{order}/approve', ApproveOrderController::class)
    ->name('orders.approve');
Route::apiResource('orders/{order}/order-items', OrderItemController::class)
    ->only(['index', 'show']);

Route::apiResource('orders/{order}/payments', PaymentController::class);
Route::get('orders/{order}/next-payment-details', NextPaymentDetailsController::class)
    ->name('orders.payments.next-payment-details');

// Digipay admin operations
Route::prefix('payments/{payment}/digipay')->name('payments.digipay.')->group(function (): void {
    Route::post('refund', [DigipayAdminController::class, 'refund'])->name('refund');
    Route::post('deliver', [DigipayAdminController::class, 'deliver'])->name('deliver');
    Route::post('reverse', [DigipayAdminController::class, 'reverse'])->name('reverse');
});
Route::post('payments/digipay/inquire-refund', [DigipayAdminController::class, 'inquireRefund'])
    ->name('payments.digipay.inquire-refund');

Route::apiResource('refunds', RefundController::class);

Route::post('orders/{order}/refund', [OrderRefundController::class, 'store'])
    ->name('orders.refund');
Route::put('refunds/{refund}/status', RefundUpdateStatusController::class)
    ->name('refunds.status');

// Discount Promotion routes
Route::get('discount-promotions/statistics', DiscountPromotionStatisticsController::class)
    ->name('discount-promotions.statistics');
Route::apiResource('discount-promotions', DiscountPromotionController::class);
Route::put('discount-promotions/{discountPromotion}/status', DiscountPromotionStatusUpdateController::class)
    ->name('discount-promotions.toggle-status');

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
Route::apiResource('enrollments', EnrollmentController::class)->except(['store'])->whereNumber('enrollment');
Route::post('enrollments/{enrollment}/change-status', ChangeEnrollmentStatusController::class)
    ->name('enrollments.change-status');
Route::post('enrollments/{enrollment}/retry-provisioning', RetryProvisioningController::class)
    ->name('enrollments.retry-provisioning');
Route::post('enrollments/{enrollment}/retry-provisioning/{provider}', RetryProvisioningController::class)
    ->name('enrollments.retry-provisioning.provider')
    ->whereIn('provider', ['ims', 'moodle', 'spotplayer', 'bbb', 'skyroom', 'moodle_quiz']);
Route::post('enrollments/{enrollment}/provisioning/resolve', ResolveProvisioningController::class)
    ->name('enrollments.provisioning.resolve');
Route::post('enrollments/{enrollment}/provisioning/waive', WaiveProvisioningController::class)
    ->name('enrollments.provisioning.waive');
Route::get('enrollments/{enrollment}/provisioning/diagnostics', ProvisioningDiagnosticsController::class)
    ->name('enrollments.provisioning.diagnostics');
Route::get('enrollments/{enrollment}/provisioning/diagnostics/advanced', AdvancedProvisioningDiagnosticsController::class)
    ->name('enrollments.provisioning.diagnostics.advanced');
Route::get('enrollments/{enrollment}/provisioning-plan/preview', [ProvisioningPlanController::class, 'preview'])
    ->name('enrollments.provisioning-plan.preview');
Route::post('enrollments/{enrollment}/provisioning-plan/apply', [ProvisioningPlanController::class, 'apply'])
    ->name('enrollments.provisioning-plan.apply');
