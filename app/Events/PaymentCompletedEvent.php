<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

final class PaymentCompletedEvent
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
