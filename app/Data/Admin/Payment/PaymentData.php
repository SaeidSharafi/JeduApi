<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class PaymentData extends Data  implements WalletTransactionSourceableDataContract
{
    public function __construct(
        public int $id,
        public int $order_id,
        public int $customer_id,
        public ?int $created_by = null,
        public int $amount,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentMethodEnum $method,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PaymentStatusEnum $status,
        public ?string $admin_notes = null,

    ) {}
}
