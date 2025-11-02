<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\CreateOrderFromCartAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Order\OrderData;
use App\Http\Controllers\Controller;

/**
 * @group Checkout
 */
final class CheckoutController extends Controller
{
    /**
     * Create order from cart.
     *
     * This endpoint allows an authenticated user to convert their shopping cart into a pending order.
     * The cart items are validated for availability and capacity before the order is created.
     *
     * @authenticated
     *
     * @responseFile storage/responses/shop/checkout/show.json
     */
    public function __invoke(CreateOrderFromCartAction $action): ApiResponseInterface
    {
        $order = $action->handle();

        return response()->created(OrderData::from($order));
    }
}
