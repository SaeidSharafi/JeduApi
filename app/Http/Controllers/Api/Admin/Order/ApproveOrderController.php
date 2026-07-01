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
     * @responseFile resources/responses/admin/order/approve.json
     *
     * @responseParam data.full_value_grand_total integer Total price of all items at their base (original) prices, excluding any discounts. Used as the reference for balance_due calculation.
     * @responseParam data.total_product_discount integer Sum of product-level discounts (featured prices, auto-promotions) across all items.
     * @responseParam data.total_cart_discount integer Total cart-level discount (coupon) applied to the order.
     * @responseParam data.total_discount integer Combined total of all discounts (product-level + cart-level).
     * @responseParam data.items.*.original_price integer Base price of the product delivery option before any product-level discounts.
     * @responseParam data.items.*.product_discount_amount integer Product-level discount amount for this item, multiplied by qty_ordered. Zero for pre-payment items.
     * @responseParam data.items.*.total_discount_amount integer Total discount on this item (product_discount_amount + discount_amount combined).
     */
    public function __invoke(Order $order, ApproveOrderAction $action): ApiResponseInterface
    {
        Gate::authorize('approve', $order);

        $order = $action->handle($order);
        $order->load('items.vendor', 'payments');

        return apiResponse()->success(
            data: OrderData::from($order),
            message: __('messages.order.approved_successfully')
        );
    }
}
