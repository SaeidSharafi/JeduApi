<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Order\OrderItem\CreateOrderItemAction;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Data\Admin\Order\OrderItemData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Order $order)
    {
        $orderItems = $order->items()->with(['vendor'])->get();

        return response()->success(OrderItemData::collect($orderItems));
    }

    /**
     * Store a newly created order item for an order.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @responseFile 201 responses/order-item/show.json
     * @responseFile 403 responses/403.json
     */
    public function store(Order $order, OrderItemCreateData $data, CreateOrderItemAction $action)
    {
        $orderItem = $action->handle($data, $order);
        $orderItem->load(['vendor']);

        return response()->created(OrderItemData::from($orderItem));
    }

    /**
     * Display the specified order item.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @responseFile 200 responses/order-item/show.json
     * @responseFile 403 responses/403.json
     */
    public function show(Order $order, OrderItem $orderItem)
    {
        $orderItem->load(['vendor']);

        return response()->success(OrderItemData::from($orderItem));
    }
}
