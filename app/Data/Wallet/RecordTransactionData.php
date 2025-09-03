<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Illuminate\Validation\Rule;

class RecordTransactionData extends Data
{
    public function __construct(
        public int $user_id,
        public TransactionTypeEnum $type,
        public int $amount, // Changed from float to int for consistency
        public TransactionSourceEnum $source_type = TransactionSourceEnum::STAFF,
        public ?int $source_id = null,
        public ?string $description = null,
        public ?array $metadata = null,
        public ?string $expires_at = null,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', Rule::enum(TransactionTypeEnum::class)],
            'amount' => ['required', 'integer'],
            'source_type' => ['required', Rule::enum(TransactionSourceEnum::class)],
            'source_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
