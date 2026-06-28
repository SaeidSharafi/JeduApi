<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\Student\CancelOrderByCustomerAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\Order\OrderData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @group Shop - Student - Orders
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
                __('messages.order.order_cancelled_successfully')
            );
        } catch (ValidationException $e) {
            return response()->error($e->getMessage(), 422);
        }
    }
}
