<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\User;
use App\Models\Admin;

class InitiateAuthAction
{
    public function execute(string $identifier, string $guard = 'user'): array
    {
        $model = $guard === 'admin' ? Admin::class : User::class;
        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
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
            if ($type === 'email') {
                return [
                    'action' => 'REGISTER',
                    'message' => 'User not found. Registration required.'
                ];
            }
            User::create(
                [
                    'phone' => $identifier,
                ]
            );
            return [
                'action' => 'OTP_REGISTER',
                'message' => 'Please request OTP to Register.'
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
