<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Data\Admin\Order\OrderUpdateData;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrderAction
{
    /**
     * Execute the action.
     */
    public function handle(OrderUpdateData $data, Order $order): Order
    {

        return DB::transaction(function () use ($data, $order): Order {
            $order->update($data->toArray());
            $order->refresh();
            OrderStatusUpdatedEvent::dispatch($order);

            return $order;
        });
    }
}
