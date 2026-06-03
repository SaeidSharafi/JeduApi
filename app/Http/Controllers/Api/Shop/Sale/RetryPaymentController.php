<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Actions\Shop\RetryOrderPaymentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Order\RetryOrderPaymentData;
use App\Exceptions\Payment\InsufficientWalletBalanceException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * @group Order History
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

        try {
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
        } catch (InsufficientWalletBalanceException $e) {
            // Return structured error for frontend to redirect to wallet top-up
            return response()->error(
                'Insufficient wallet balance',
                422,
                [
                    'error_code'          => 'INSUFFICIENT_WALLET_BALANCE',
                    'available_balance'   => $e->availableBalance,
                    'required_balance'    => $e->requiredBalance,
                    'shortfall'           => $e->shortfall,
                    'redirect_suggestion' => 'wallet-topup',
                ]
            );
        }
    }
}
