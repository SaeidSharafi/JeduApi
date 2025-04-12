<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\User;
use App\Models\Admin;

class InitiateAuthAction
{
    public function execute(string $identifier, string $type, string $guard = 'user'): array
    {
        $model = $guard === 'admin' ? Admin::class : User::class;

        $user = $model::when(
            $type === 'email',
            fn ($q) => $q->where('email', $identifier),
            fn ($q) => $q->where('phone', $identifier)
        )->first();

        if (!$user) {
            if ($guard === 'admin') {
                return [
                    'action' => 'NOT_FOUND',
                    'message' => 'Admin account not found.'
                ];
            }
            return [
                'action' => 'REGISTER',
                'message' => 'User not found. Registration required.'
            ];
        }

        if ($user->hasSetPassword()) {
            return [
                'action' => 'PASSWORD_LOGIN',
                'message' => 'Please login with password.'
            ];
        }

        return [
            'action' => 'OTP_LOGIN',
            'message' => 'Please request OTP to login.'
        ];
    }
}
