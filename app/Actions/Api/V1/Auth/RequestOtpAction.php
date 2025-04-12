<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\Admin;
use App\Models\User;

class RequestOtpAction
{
    public function __construct(
        protected GenerateOtpAction $generateOtp
    ) {
    }

    public function execute(string $identifier, string $type, string $purpose, string $guard = 'user'): array
    {
        $model = $guard === 'admin' ? Admin::class : User::class;

        $user = $model::when(
            $type === 'email',
            fn ($q) => $q->where('email', $identifier),
            fn ($q) => $q->where('phone', $identifier)
        )->first();

        // Always return success for users to prevent enumeration
        // For admins we want to fail fast if account doesn't exist
        if (!$user) {
            if ($guard === 'admin') {
                return [
                    'status' => 'error',
                    'message' => 'Admin account not found.'
                ];
            }
            return [
                'status' => 'success',
                'message' => 'If the user exists, an OTP has been sent.'
            ];
        }

        $this->generateOtp->execute($user);

        return [
            'status' => 'success',
            'message' => 'OTP has been sent.'
        ];
    }
}
