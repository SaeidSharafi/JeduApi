<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Actions\Shop\CreateOrderFromCartAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Cart\CheckoutData;
use App\Data\Shop\Cart\CheckoutResponseData;
use App\Data\Shop\Student\Order\OrderData;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

/**
 * @group Shop - Checkout
 *
 * @authenticated
 */
final class CheckoutController extends Controller
{
    /**
     * Create order from cart with payment processing.
     *
     * This endpoint allows an authenticated user to convert their shopping cart into an order.
     *
     * **Payment Flow:**
     * - **Free Orders (grand_total = 0):** Automatically completed with NO_PAYMENT, no payment_method needed
     * - **Wallet Payment:** Immediate completion if sufficient balance, order finalized instantly
     * - **Bank Transfer:** Creates pending order awaiting manual payment verification by admin
     * - **Online Gateway:** Returns redirect_url to payment gateway, order pending until callback verification
     *
     * The cart items are validated for availability and capacity before the order is created.
     * Upon successful checkout, the user's cart is automatically deleted.
     *
     * **Multi-Step Payment Gateways:**
     * When using payment methods that require redirect (e.g., mellat_gateway), the response will include:
     * - `redirect_url`: The URL to redirect the customer to for payment
     * - `redirect_method`: HTTP method to use (GET or POST)
     * - `redirect_data`: Optional form data to submit (for POST redirects)
     *
     * @responseFile resources/responses/shop/checkout/show.json
     *
     * @response 422 scenario="insufficient wallet balance" {
     *   "message": "Validation error.",
     *   "errors": {
     *     "wallet_balance": "Insufficient wallet balance."
     *    },
     *   "metadata": {
     *     "error_code": "INSUFFICIENT_WALLET_BALANCE",
     *     "available_balance": 500000,
     *     "required_balance": 1000000,
     *     "shortfall": 500000
     *   }
     * }
     *
     */
    public function __invoke(CheckoutData $data, CreateOrderFromCartAction $action): ApiResponseInterface
    {
        if (! auth('user')->check()) {
            throw ValidationException::withMessages([
                'auth' => [__('validation.custom.checkout.user_not_authenticated')],
            ]);
        }

        $result = $action->handle($data, auth()->user());

        // Build response with order data and optional redirect information
        $order     = $result->payment->order->fresh(['items.productDeliveryOption.product', 'customer', 'payments.transactions']);
        $orderData = OrderData::from($order);

        if ($result->redirect_url) {
            // Multi-step payment requiring redirect
            $responseData = CheckoutResponseData::withRedirect(
                order: $orderData,
                redirectUrl: $result->redirect_url,
                redirectData: $result->redirect_data,
                method: $result->redirect_method
            );
        } else {
            // Single-step payment completed
            $responseData = CheckoutResponseData::completed($orderData);
        }

        return response()->created($responseData);
    }
}
