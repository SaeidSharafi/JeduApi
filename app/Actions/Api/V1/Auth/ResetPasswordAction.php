<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ResetPasswordAction
{
    public function __construct(
        protected VerifyOtpAction $verifyOtp
    ) {
    }

    public function execute(
        string $identifier,
        string $type,
        string $otp,
        string $password,
        string $guard = 'user'
    ): void {
        $model = $guard === 'admin' ? Admin::class : User::class;

        $user = $model::when(
            $type === 'email',
            fn ($q) => $q->where('email', $identifier),
            fn ($q) => $q->where('phone', $identifier)
        )->firstOrFail();

        // Verify OTP first
        $this->verifyOtp->execute($user, $otp);

        // If OTP is valid, update password
        $user->password = Hash::make($password);
        $user->save();
    }
}
