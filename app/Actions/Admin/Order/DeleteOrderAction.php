<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Services\ProductReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteOrderAction
{
    public function __construct(
        private ProductReservationService $productReservationService,
    ) {}

    /**
     * Execute the action.
     */
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            if ($order->status !== OrderStatusEnum::PENDING) {
                throw ValidationException::withMessages([
                    'order' => __('messages.order.cannot_delete_non_pending_order'),
                ]);
            }
            $order->load('payments');
            if ($order->payments->isNotEmpty() && $order->payments->sum('amount') > 0) {
                throw ValidationException::withMessages([
                    'order' => __('messages.order.cannot_delete_order_with_payments', ['order_id' => $order->increment_id]),
                ]);
            }

            // Release any reserved seats (order is unpaid PENDING)
            $order->load('items');
            foreach ($order->items as $item) {
                $this->productReservationService->release($item->product_delivery_option_id, $item->qty_ordered);
            }

            $order->delete();
        });
    }
}
