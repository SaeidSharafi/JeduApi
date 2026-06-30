<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Data\OtpManager\SentOtpDto;

final readonly class AuthInitiationResultData
{
    private function __construct(
        public ?SentOtpDto $otpSent = null,
        public bool $requiresPassword = false,
    ) {}

    public static function otp(SentOtpDto $otpSent): self
    {
        return new self(otpSent: $otpSent, requiresPassword: false);
    }

    public static function password(): self
    {
        return new self(otpSent: null, requiresPassword: true);
    }
}
