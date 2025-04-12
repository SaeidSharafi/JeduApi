<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class VerifyOtpForResetAction
{
    public function __construct(
        protected VerifyOtpAction $verifyOtp
    ) {
    }

    public function execute(string $identifier, string $type, string $otp, string $guard = 'user'): array
    {
        $model = $guard === 'admin' ? Admin::class : User::class;

        $user = $model::when(
            $type === 'email',
            fn ($q) => $q->where('email', $identifier),
            fn ($q) => $q->where('phone', $identifier)
        )->firstOrFail();

        $this->verifyOtp->execute($user, $otp);

        $resetToken = Str::random(60);
        $user->update(['reset_token' => Hash::make($resetToken)]);

        return [
            'reset_token' => $resetToken,
            'message' => 'OTP verified successfully. Use this token to reset password.'
        ];
    }
}
