<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

final class GiftTransactionNotFoundException extends WalletException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Gift transaction {$transactionId} not found for the given wallet.");
    }

    public function errorCode(): string
    {
        return 'GIFT_TRANSACTION_NOT_FOUND';
    }

    /**
     * @return array<string, mixed>
     */
    protected function customMetadata(): array
    {
        return ['transaction_id' => $this->transactionId];
    }
}
