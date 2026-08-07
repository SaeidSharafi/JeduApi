<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Order\OrderItemData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Order Items
 *
 * Handles order item management for orders.
 * This controller allows administrators to view, create, and show order items associated with orders.
 *
 * @authenticated
 */
final class OrderItemController extends Controller
{
    /**
     * Display a listing of the order items for a specific order.
     *
     */
    public function index(Order $order): ApiResponseInterface
    {
        Gate::authorize('view', $order);

        $orderItems = $order->items()->with(['vendor'])->get();

        return apiResponse()->success(OrderItemData::collect($orderItems));
    }

    /**
     * Display the specified order item.
     *
     * @responseFile 200 resources/responses/admin/order-item/show.json
     * @responseFile 403 resources/responses/403.json
     */
    public function show(Order $order, OrderItem $orderItem): ApiResponseInterface
    {
        Gate::authorize('view', $order);

        $orderItem->load(['vendor']);

        return apiResponse()->success(OrderItemData::from($orderItem));
    }
}
