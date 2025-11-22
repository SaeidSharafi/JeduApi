<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Order\ApproveOrderAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Order\OrderData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Orders
 *
 * @authenticated
 */
final class ApproveOrderController extends Controller
{
    /**
     * Approve order for fulfillment and provisioning.
     *
     * This endpoint allows authorized staff to manually approve orders for completion
     * and enrollment provisioning. This is used when the provisioning trigger is set
     * to 'manual_approval' or when staff needs to override the automatic provisioning.
     *
     * The order must have sufficient payment coverage considering prepayment options.
     *
     * @responseFile storage/responses/admin/order/approve.json
     */
    public function __invoke(Order $order, ApproveOrderAction $action): ApiResponseInterface
    {
        Gate::authorize('approve', $order);

        $order = $action->handle($order);
        $order->load('items.vendor', 'payments');

        return response()->success(
            data: OrderData::from($order),
            message: __('messages.order.approved_successfully')
        );
    }
}
