<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Admin\Order\OrderListItemData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\RefundStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class RefundData extends Data implements WalletTransactionSourceableDataContract
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $deduction_amount,
        public readonly RefundTransactionData $transaction_details,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public readonly RefundStatusEnum $status,
        public readonly ?string $admin_notes,
        public ?Verta $created_at,
        public ?Verta $updated_at,
        public OrderListItemData $order,
    ) {}

}
