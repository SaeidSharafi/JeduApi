<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RefundCompletedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Refund $refund
    ) {}
}
