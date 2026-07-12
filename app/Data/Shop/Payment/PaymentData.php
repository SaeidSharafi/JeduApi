<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class PaymentData extends Data
{
    public function __construct(
        public string $uuid,
        public int $id,
        public int $amount,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentMethodEnum $method,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentStatusEnum $status,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentPurposeEnum $purpose,
        public ?string $last_gateway_reference = null,
        public int $attempt_count = 0,
        #[DataCollectionOf(PaymentTransactionData::class)]
        public ?Collection $transactions = null,
    ) {}
}
