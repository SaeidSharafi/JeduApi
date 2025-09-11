<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Data\Admin\Order\OrderUpdateData;
use App\Enums\EnrolmentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
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
            $order->load('enrolments');
            if ($order->status    === OrderStatusEnum::CANCELLED
                || $order->status === OrderStatusEnum::REFUNDED
            ) {
                $order->enrolments->each(function ($enrolment) {
                    $enrolment->update(['enrollment_status' => EnrolmentStatusEnum::CANCELLED]);
                });
            }

            OrderStatusUpdatedEvent::dispatch($order);

            return $order;
        });
    }
}
