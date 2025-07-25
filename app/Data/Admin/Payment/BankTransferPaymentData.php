<?php

namespace App\Data\Admin\Payment;

use App\Data\Transformer\CarbonFromJalaliString;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class BankTransferPaymentData extends Data
{
    public function __construct(
        public ?string $transaction_id,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?string $transaction_date,
        public ?string $sender_name,
        public ?string $notes,
    )
    {
    }
}
