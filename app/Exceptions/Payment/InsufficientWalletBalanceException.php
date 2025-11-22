<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use Exception;

final class InsufficientWalletBalanceException extends Exception
{
    public function __construct(
        public readonly int $availableBalance,
        public readonly int $requiredBalance,
        public readonly int $shortfall,
        string $message = ''
    ) {
        $message = $message ?: __('validation.custom.insufficient_wallet_balance');
        parent::__construct($message);
    }

    public function getAvailableBalance(): int
    {
        return $this->availableBalance;
    }

    public function getRequiredBalance(): int
    {
        return $this->requiredBalance;
    }

    public function getShortfall(): int
    {
        return $this->shortfall;
    }
}
