<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

interface PendingPaymentPreparerContract
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function handle(
        Authenticatable $actor,
        int $customerId,
        PaymentMethodEnum $method,
        PaymentPurposeEnum $purpose,
        int $amount,
        ?Order $order = null,
        ?string $adminNotes = null,
        ?array $data = null
    ): Payment;
}
