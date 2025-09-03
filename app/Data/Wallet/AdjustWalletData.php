<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class AdjustWalletData extends Data
{
    public function __construct(
        public int $user_id,
        public int $amount, // Can be positive or negative
        public string $reason, // Required for adjustment - explains why
        public ?string $description = null,
        public ?array $metadata = null,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'integer', 'not_in:0'], // Cannot be zero
            'reason' => ['required', 'string', 'max:255'], // Required reason for adjustment
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
