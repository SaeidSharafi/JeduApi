<?php

namespace App\Actions\Auth;

use App\Enums\OtpType;
use App\Exceptions\InvalidOtpCode;
use App\Exceptions\UserNotFoundException;
use App\Models\Admin;
use App\Models\User;
use App\Services\OtpManagerService;

class VerifyOtpAction extends AuthAction
{
    public function __construct(
        protected OtpManagerService $otpManagerService,
        protected AuthenticateUserAction $authenticateUserAction
    )
    {
    }

    public function execute(string|Admin|User $identifier, string $trackingCode, string $otpCode, OtpType $otpType, string $guard = 'user'): Admin|User
    {
        $user = $identifier;
        if (is_string($identifier)) {
            $user = $this->getUser($identifier, $guard);
        }

        if (!$user) {
            throw new UserNotFoundException();
        }
        if (!$this->otpManagerService->verify($user->phone, $guard, $otpCode, $trackingCode, $otpType)){
            throw new InvalidOtpCode();
        }

        return $user;

    }
}
