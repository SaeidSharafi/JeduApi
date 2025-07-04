<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class OrderCreatedEvent
{
    use Dispatchable;

    public function __construct(public Order $order)
    {
    }
}
