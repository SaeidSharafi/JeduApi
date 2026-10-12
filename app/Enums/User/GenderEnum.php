<?php

declare(strict_types=1);

namespace App\Enums\User;

use App\Traits\AdvanceEnum;

enum GenderEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case MALE   = 'male';
    case FEMALE = 'female';

    /**
     * Map to the integer value IMS expects (1 = male, 2 = female).
     */
    public function toImsValue(): int
    {
        return match ($this) {
            self::MALE   => 1,
            self::FEMALE => 2,
        };
    }
}
