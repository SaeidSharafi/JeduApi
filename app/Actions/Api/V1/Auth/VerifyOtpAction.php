<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Validation\ValidationException;

class VerifyOtpAction
{
    public function execute(User|Admin $user, string $otp): bool
    {
        if ($user->otp !== $otp || now()->isAfter($user->otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP code is invalid or has expired.'],
            ]);
        }

        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        return true;
    }
}
