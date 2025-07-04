<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Data\Order\OrderCreateData;
use App\Data\Order\OrderUpdateData;
use App\Data\Product\ProductData;
use App\Data\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Enums\OrderItemStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
