<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use App\Data\Wallet\WalletTransactionData;
use Spatie\LaravelData\Data;

final class GiftAllocationResultData extends Data
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?WalletTransactionData $transaction = null,
        public ?array $errors = null
    ) {}

    /**
     * Create successful allocation result
     */
    public static function success(string $message, WalletTransactionData $transaction): self
    {
        return new self(
            success: true,
            message: $message,
            transaction: $transaction,
            errors: null
        );
    }

    /**
     * Create failed allocation result
     */
    public static function failure(string $message, ?array $errors = null): self
    {
        return new self(
            success: false,
            message: $message,
            transaction: null,
            errors: $errors
        );
    }
}
