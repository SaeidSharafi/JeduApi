<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Nette\Schema\ValidationException;

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
                throw new ValidationException('Cannot delete order with payments that have a positive amount.');
            }

            $order->delete();
        });
    }
}
