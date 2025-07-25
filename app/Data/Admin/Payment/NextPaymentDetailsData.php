<?php

namespace App\Data\Admin\Payment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Payment\NextPaymentTypeEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class NextPaymentDetailsData extends Data
{
    public function __construct(
        public int $amount_due,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public NextPaymentTypeEnum $payment_type,
        public string $summary_description,
        public array $line_item_details,
    )
    {
    }
}
