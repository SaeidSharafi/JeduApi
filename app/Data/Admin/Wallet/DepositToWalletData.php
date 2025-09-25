<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DepositToWalletData extends Data
{
    public function __construct(
        public int $amount,
        public ?string $description = null,
        public ?array $metadata = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'amount'      => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata'    => ['nullable', 'array'],
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
            'amount' => [
                'description' => 'Amount to deposit into wallet.',
                'example'     => 20000,
            ],
            'description' => [
                'description' => 'Optional description for the deposit.',
                'example'     => 'Deposit for campaign bonus.',
            ],
            'metadata' => [
                'description' => 'Additional metadata for the deposit.',
                'example'     => ['source' => 'admin_panel'],
            ],
        ];
    }
}
