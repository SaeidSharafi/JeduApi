<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class PaymentTransactionData extends Data
{
    public function __construct(
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentTransactionStatusEnum $status,
        public ?Verta $initiated_at = null,
        public ?Verta $completed_at = null,
    ) {}
}
