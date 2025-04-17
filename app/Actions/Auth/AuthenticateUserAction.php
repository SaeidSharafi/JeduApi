<?php

namespace App\Actions\Auth;

use App\Enums\OtpType;
use App\Exceptions\InvalidOtpCode;
use App\Models\Admin;
use App\Models\User;
use App\Services\OtpManagerService;
use Laravel\Sanctum\NewAccessToken;

class AuthenticateUserAction
{
    public function __construct(
        protected OtpManagerService $otpManager,
    ) {}

    /**
     * @param  string  $identifier
     * @param  string  $trackingCode
     * @param  string  $otpCode
     * @param  OtpType  $otpType
     * @return string generated bearer token
     *
     * @throws InvalidOtpCode
     */
    public function execute(Admin|User $user, string $guard = 'user'): NewAccessToken
    {
        $tokenName = $guard === 'admin' ? 'admin_token' : 'auth_token';

        return $user->createToken($tokenName);

    }
}
