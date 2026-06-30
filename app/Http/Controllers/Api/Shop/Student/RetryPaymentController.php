<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

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
     * @responseFile resources/responses/shop/order/retry-payment.json
     */
    public function __invoke(
        string $incrementId,
        RetryOrderPaymentData $data,
        RetryOrderPaymentAction $action
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

        // Return response based on payment type
        if ($result->requiresRedirect()) {
            return response()->success([
                'message'           => 'Payment initiated. Please complete payment at the gateway.',
                'payment'           => $result->payment,
                'requires_redirect' => true,
                'redirect_url'      => $result->redirect_url,
                'redirect_data'     => $result->redirect_data,
                'redirect_method'   => $result->redirect_method,
            ]);
        }

        return response()->success([
            'message'           => 'Payment completed successfully.',
            'payment'           => $result->payment,
            'requires_redirect' => false,
        ]);
    }
}
