<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Events\CustomerAuthenticatedEvent;
use App\Models\Staff;
use App\Models\User;
use App\Services\OtpManagerService;
use Laravel\Sanctum\NewAccessToken;

final class AuthenticateUserAction
{
    public function __construct(
        private OtpManagerService $otpManager,
    ) {}

    public function execute(Staff|User $user, string $guard = 'user'): NewAccessToken
    {
        $tokenName = $guard === 'staff' ? 'staff_token' : 'auth_token';

        $token = $user->createToken($tokenName);
        if ($guard === 'user') {
            event(new CustomerAuthenticatedEvent(request(), $user));
        }

        return $token;
    }
}
