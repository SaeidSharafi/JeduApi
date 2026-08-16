<?php

declare(strict_types=1);

namespace App\Enums\Wallet;

use App\Traits\AdvanceEnum;

enum TransactionTypeEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case DEPOSIT    = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case PAYMENT    = 'payment';
    case REFUND     = 'refund';
    case GIFT       = 'gift';
    case BONUS      = 'bonus';
    case ADJUSTMENT = 'adjustment';
    case EXPIRY     = 'expiry';

    public function isDebit(): bool
    {
        return in_array($this, [self::WITHDRAWAL, self::PAYMENT, self::EXPIRY]);
    }

    public function isGift(): bool
    {
        return in_array($this, [self::GIFT, self::BONUS]);
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::DEPOSIT, self::REFUND, self::GIFT, self::BONUS]);
    }
}
