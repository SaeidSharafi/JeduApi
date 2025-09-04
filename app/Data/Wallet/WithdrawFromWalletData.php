<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class WithdrawFromWalletData extends Data
{
    public function __construct(
        public int $amount,
        public ?string $description = null,
        public ?array $metadata = null,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'amount'      => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata'    => ['nullable', 'array'],
        ];
    }
}
