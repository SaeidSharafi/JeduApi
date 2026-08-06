<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Data\Admin\Order\OrderUpdateData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use App\Services\ProductReservationService;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrderAction
{
    public function __construct(
        private ProductReservationService $productReservationService,
    ) {}

    /**
     * Execute the action.
     */
    public function handle(OrderUpdateData $data, Order $order): Order
    {

        return DB::transaction(function () use ($data, $order): Order {
            $order->update($data->toArray());
            $order->refresh();
            $order->load('enrollments');
            if ($order->status    === OrderStatusEnum::CANCELLED
                || $order->status === OrderStatusEnum::REFUNDED
            ) {
                $order->load('items');
                foreach ($order->items as $item) {
                    if ($item->status === OrderItemStatusEnum::PENDING) {
                        $this->productReservationService->release($item->product_delivery_option_id, $item->qty_ordered);
                    }
                }
                $order->enrollments->each(function ($enrollment): void {
                    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::CANCELLED]);
                });
            }

            OrderStatusUpdatedEvent::dispatch($order);

            return $order;
        });
    }
}
