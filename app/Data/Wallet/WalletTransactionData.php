<?php

namespace App\Data\Wallet;

use App\Contracts\ProductableDataContract;
use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Casts\TrasnactionSourceCast;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\MorphTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\User;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class WalletTransactionData extends Data
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
        #[WithCast(TrasnactionSourceCast::class, short: false)]
        public ?WalletTransactionSourceableDataContract $source,
        public ?string $description = null,
        public ?array $metadata = null,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $expires_at = null,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $created_at = null,
    )
    {
    }
}
