<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use App\Enums\Wallet\WalletStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateWalletData extends Data
{
    public function __construct(
        public int $balance,
        public int $gift_balance,
        public string $status
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'balance'      => ['required', 'integer', 'min:0'],
            'gift_balance' => ['integer', 'min:0'],
            'status'       => ['required', Rule::enum(WalletStatusEnum::class)],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'user_id' => [
                'description' => 'User ID for the wallet.',
                'example'     => 1,
            ],
            'balance' => [
                'description' => 'Initial wallet balance.',
                'example'     => 100000,
            ],
            'gift_balance' => [
                'description' => 'Initial gift balance.',
                'example'     => 5000,
            ],
            'status' => [
                'description' => 'Wallet status value.',
                'example'     => 'active',
            ],
        ];
    }
}
