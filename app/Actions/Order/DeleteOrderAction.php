<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

final readonly class DeleteOrderAction
{
    /**
     * Execute the action.
     */
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->delete();
        });
    }
}
