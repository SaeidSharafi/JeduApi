<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\Payment\BuildPaymentResponseAction;
use App\Actions\Shop\RetryOrderPaymentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\Order\RetryOrderPaymentData;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Student - Orders
 *
 * @authenticated
 */
final class RetryPaymentController extends Controller
{
    /**
     * Retry payment for a pending order.
     *
     * This endpoint allows customers to retry payment on orders that are still PENDING
     * with failed or incomplete payment attempts. The order must belong to the authenticated
     * user and have an outstanding balance.
     *
     * @responseFile 200 scenario="gateway redirect required" resources/responses/shop/order/retry-payment.json
     * @responseFile 200 scenario="payment completed without gateway redirect" resources/responses/shop/payment/retry-payment-successful.json
     * @responseFile 422 scenario="validation rejected before payment processing" resources/responses/shop/payment/retry-validation-error.json
     * @responseFile 409 scenario="saved order payment rejected" resources/responses/shop/payment/order-payment-conflict.json
     * @responseFile 502 scenario="gateway initiation rejected" resources/responses/shop/payment/order-gateway-rejected.json
     * @responseFile 504 scenario="gateway timeout; outcome unknown" resources/responses/shop/payment/order-gateway-timeout.json
     * @responseFile 500 scenario="unexpected processing failure with saved recovery data" resources/responses/shop/payment/order-processing-error.json
     */
    public function __invoke(
        string $incrementId,
        RetryOrderPaymentData $data,
        RetryOrderPaymentAction $action,
        BuildPaymentResponseAction $responseAction,
    ): ApiResponseInterface {
        $user = Auth::guard('user')->user();

        // Find the order (must belong to authenticated user)
        $order = $user->orders()
            ->where('increment_id', $incrementId)
            ->firstOrFail();

        // Process payment retry
        $result = $action->handle(
            order: $order,
            paymentMethod: $data->payment_method,
            amountToPay: $order->grand_total
        );

        return apiResponse()->success($responseAction->initiationResponse($result), $responseAction->initiationMessage($result));
    }
}
