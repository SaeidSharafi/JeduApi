<?php

namespace App\Actions\Auth;

use App\Enums\OtpType;
use App\Services\OtpManagerService;

class VertifyOtpAction
{
    public function execute(string $identifier, string $trackingCode, string $otpCode, OtpType $otpType, string $guard = 'user'): bool
    {
        return app(OtpManagerService::class)->verify($identifier, $guard, $otpCode, $trackingCode, $otpType);
    }
}
