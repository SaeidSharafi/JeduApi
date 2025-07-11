<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class OrderStatusUpdatedEvent
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
