<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Casts\TransactionSourceCast;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\User;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class WalletTransactionData extends Data
{
    public function __construct(
        public int $id,
        public WalletData $wallet,
        public User $user,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public TransactionTypeEnum $type,
        public int $amount,
        public int $balance_after,
        public int $gift_balance_after,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public TransactionSourceEnum $source_type,
        #[WithCast(TransactionSourceCast::class, short: false)]
        public ?WalletTransactionSourceableDataContract $source,
        public ?string $description = null,
        public ?array $metadata = null,
        public ?Verta $expires_at = null,
        public ?Verta $created_at = null,
    ) {}
}
