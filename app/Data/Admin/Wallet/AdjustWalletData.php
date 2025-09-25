<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AdjustWalletData extends Data
{
    public function __construct(
        public int $amount, // Can be positive or negative
        public string $reason, // Required for adjustment - explains why
        public ?string $description = null,
        public ?array $metadata = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'amount'      => ['required', 'integer', 'not_in:0'], // Cannot be zero
            'reason'      => ['required', 'string', 'max:255'], // Required reason for adjustment
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
                'description' => 'Amount to adjust (positive or negative, cannot be zero).',
                'example'     => 5000,
            ],
            'reason' => [
                'description' => 'Reason for the wallet adjustment.',
                'example'     => 'Correction after failed transaction.',
            ],
            'description' => [
                'description' => 'Optional description for the adjustment.',
                'example'     => 'Manual adjustment by admin.',
            ],
            'metadata' => [
                'description' => 'Additional metadata for the adjustment.',
                'example'     => ['source' => 'admin_panel'],
            ],
        ];
    }
}
