<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use App\Data\Admin\User\ShowUserData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Wallet\WalletStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class WalletData extends Data
{
    public function __construct(
        public int $balance,
        public int $gift_balance,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public WalletStatusEnum $status,
        public ?ShowUserData $user = null,
    ) {
    }

    public function exceptProperties(): array
    {
        return $this->user === null ? ['user'] : [];
    }
}
