<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Staff;
use App\Models\User;
use App\Services\OtpManagerService;
use Laravel\Sanctum\NewAccessToken;

final class AuthenticateUserAction
{
    public function __construct(
        protected OtpManagerService $otpManager,
    ) {}

    public function execute(Staff|User $user, string $guard = 'user'): NewAccessToken
    {
        $tokenName = $guard === 'staff' ? 'staff_token' : 'auth_token';

        return $user->createToken($tokenName);

    }
}
