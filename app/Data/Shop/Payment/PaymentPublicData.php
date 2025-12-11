<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class PaymentPublicData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $amount,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public readonly PaymentMethodEnum $method,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public readonly PaymentStatusEnum $status,
        public readonly ?string $last_gateway_reference = null,
        public readonly int $attempt_count = 0,
        #[DataCollectionOf(PaymentTransactionData::class)]
        public readonly ?Collection $transactions = null,
    ) {}

    public static function fromPayment(Payment $payment): self
    {
        $transactions = $payment->relationLoaded('transactions')
            ? $payment->transactions
            : null;

        return new self(
            id: $payment->id,
            amount: $payment->amount,
            method: $payment->method,
            status: $payment->status,
            last_gateway_reference: $payment->last_gateway_reference,
            attempt_count: $payment->attempt_count ?? 0,
            transactions: $transactions,
        );
    }
}
