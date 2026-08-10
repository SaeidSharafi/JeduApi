<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use Spatie\LaravelData\Data;

final class BankTransferPaymentData extends Data
{
    public function __construct(
        public ?string $transaction_id,
        public ?string $transaction_date,
        public ?string $sender_name,
        public ?string $notes,
    ) {}
}
