<?php

namespace App\Data\Wallet;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class WalletData extends Data
{
    public function __construct(
        public int $balance,
        public int $gift_balance,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public WalletStatusEnum $status,
        public ?User $user = null,
    )
    {
    }
}
