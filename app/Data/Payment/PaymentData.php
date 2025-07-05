<?php

namespace App\Data\Payment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class PaymentData extends Data
{
    public function __construct(
        public int $id,
        public int $order_id,
        public int $customer_id,
        public ?int $staff_id = null,
        public int $amount,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentMethodEnum $method,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentStatusEnum $status,
        public ?string $admin_notes = null,

    )
    {
    }
}
