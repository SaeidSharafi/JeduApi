<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Actions\Shop\Order\CancelOrderByCustomerAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Order\OrderData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * @group Order History
 *
 * @authenticated
 */
final class CancelOrderController extends Controller
{
    public function __construct(
        private readonly CancelOrderByCustomerAction $action
    ) {}

    /**
     * Cancel an order.
     *
     * This endpoint allows a customer to cancel their own pending order.
     * Orders can only be cancelled if they are in pending status and have no completed payments.
     * Once an order has been paid (fully or partially), cancellation is not allowed and the customer
     * must contact support for refund assistance.
     *
     * @responseFile resources/responses/shop/order/show.json
     */
    public function __invoke(Order $order): ApiResponseInterface
    {
        try {
            $cancelledOrder = $this->action->execute($order, Auth::id());

            return response()->success(
                OrderData::from($cancelledOrder),
                'Order cancelled successfully'
            );
        } catch (DomainException $e) {
            return response()->error($e->getMessage(), 422);
        }
    }
}
