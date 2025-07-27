<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use Spatie\LaravelData\Data;

final class RefundTransactionData extends Data
{
    public function __construct(
        public readonly ?string $receiver_name = null,
        public readonly ?string $card_number = null,
        public readonly ?string $iban_number = null,
        public readonly ?string $tracking_code = null,
    ) {}
}
