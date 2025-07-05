<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Order\OrderItem\CreateOrderItemAction;
use App\Data\Order\OrderItemCreateData;
use App\Data\Order\OrderItemData;
use App\Data\Order\OrderItemListItemData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function index(Order $order)
    {
        $orderItems = $order->items()->with(['vendor'])->get();
        return response()->success(OrderItemData::collect($orderItems));
    }

    public function store(Order $order, OrderItemCreateData $data, CreateOrderItemAction $action)
    {
        $orderItem = $action->handle($data, $order);
        $orderItem->load(['vendor']);
        return response()->created(OrderItemData::from($orderItem));
    }

    public function show(Order $order, OrderItem $orderItem)
    {
        $orderItem->load(['vendor']);
        return response()->success(OrderItemData::from($orderItem));
    }
}
