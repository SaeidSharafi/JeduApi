<?php

declare(strict_types=1);

namespace App\Exceptions\Wallet;

final class GiftAlreadyFullyReclaimedException extends WalletException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Gift transaction {$transactionId} has no unspent amount left to reclaim.");
    }

    public function errorCode(): string
    {
        return 'GIFT_ALREADY_FULLY_RECLAIMED';
    }

    /**
     * @return array<string, mixed>
     */
    protected function customMetadata(): array
    {
        return ['transaction_id' => $this->transactionId];
    }
}
