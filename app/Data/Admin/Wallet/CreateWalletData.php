<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use App\Enums\Wallet\WalletStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreateWalletData extends Data
{

    public function __construct(
        public int $user_id,
        public int $balance,
        public int $gift_balance = 0,
        public string $status
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'balance' => ['required', 'integer', 'min:0'],
            'gift_balance' => ['integer', 'min:0'],
            'status' => ['required', Rule::enum(WalletStatusEnum::class)],
        ];
    }
}


