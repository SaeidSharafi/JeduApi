<?php

namespace App\Enums;

use App\Contracts\OtpTypeInterface;

enum OtpType: string implements OtpTypeInterface
{
    case SIGNUP = 'SIGNUP';
    case RESET_PASSWORD = 'RESET_PASSWORD';
    case SIGNIN = 'SIGNIN';

    /**
     * {@inheritDoc}
     */
    public function identifier(): string
    {
        return $this->value;
    }
}
