<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Actions\Shop\CreateOrderFromCartAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Cart\CheckoutData;
use App\Data\Shop\Order\OrderData;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

/**
 * @group Checkout
 */
final class CheckoutController extends Controller
{
    /**
     * Create order from cart with payment method.
     *
     * This endpoint allows an authenticated user to convert their shopping cart into an order.
     * If payment_method is "wallet", the payment is processed immediately and the order is completed.
     * If payment_method is "bank_transfer", a pending order is created awaiting payment confirmation.
     * The cart items are validated for availability and capacity before the order is created.
     * Upon successful checkout, the user's cart is automatically deleted.
     *
     * @authenticated
     *
     * @responseFile storage/responses/shop/checkout/show.json
     */
    public function __invoke(CheckoutData $data, CreateOrderFromCartAction $action): ApiResponseInterface
    {
        if (! auth('user')->check()) {
            throw ValidationException::withMessages([
                'auth' => [__('validation.custom.checkout.user_not_authenticated')],
            ]);
        }
        $order = $action->handle($data, auth()->user());

        return response()->created(OrderData::from($order));
    }
}
