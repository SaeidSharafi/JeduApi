<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteOrderAction
{
    /**
     * Execute the action.
     */
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->load('payments');
            if ($order->payments->isNotEmpty() && $order->payments->sum('amount') > 0) {
                throw ValidationException::withMessages([
                   'order' => __('messages.order.cannot_delete_order_with_payments', ['order_id' => $order->increment_id])
                ]);
            }

            $order->delete();
        });
    }
}
