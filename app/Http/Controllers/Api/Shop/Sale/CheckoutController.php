<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Actions\Shop\CreateOrderFromCartAction;
use App\Actions\Shop\Payment\BuildPaymentResponseAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Cart\CheckoutData;
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
     * Once the order is saved, the cart is deleted. Payment-stage failures return the saved order identifier in data.
     *
     * **Multi-Step Payment Gateways:**
     * When using payment methods that require redirect (e.g., mellat_gateway), the response will include:
     * - `redirect_url`: The URL to redirect the customer to for payment
     * - `redirect_method`: HTTP method to use (GET or POST)
     * - `redirect_data`: Optional form data to submit (for POST redirects)
     *
     * @responseFile 201 resources/responses/shop/checkout/show.json
     * @responseFile 201 scenario="payment completed without gateway redirect" resources/responses/shop/payment/checkout-payment-successful.json
     * @responseFile 422 scenario="validation rejected before payment processing" resources/responses/shop/payment/payment-validation-error.json
     * @responseFile 409 scenario="saved order payment rejected" resources/responses/shop/payment/order-payment-conflict.json
     * @responseFile 502 scenario="gateway initiation rejected" resources/responses/shop/payment/order-gateway-rejected.json
     * @responseFile 504 scenario="gateway timeout; outcome unknown" resources/responses/shop/payment/order-gateway-timeout.json
     * @responseFile 500 scenario="unexpected processing failure with saved recovery data" resources/responses/shop/payment/order-processing-error.json
     * @responseFile 500 scenario="free order completion rolled back; order saved" resources/responses/shop/payment/free-order-processing-error.json
     */
    public function __invoke(CheckoutData $data, CreateOrderFromCartAction $action, BuildPaymentResponseAction $responseAction): ApiResponseInterface
    {
        if (! auth('user')->check()) {
            throw ValidationException::withMessages([
                'auth' => [__('validation.custom.checkout.user_not_authenticated')],
            ]);
        }

        $result = $action->handle($data, auth()->user());

        return apiResponse()->created($responseAction->checkoutResponse($result), $responseAction->initiationMessage($result));
    }
}
