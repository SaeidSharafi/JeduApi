<?php

namespace App\Actions\Api\V1\Auth;

use App\Enums\OtpType;
use App\Exceptions\InvalidOtpCode;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;
use App\Services\OtpManagerService;
use Illuminate\Support\Facades\Hash;

class ResetPasswordAction extends AuthAction
{
    public function __construct(
        protected OtpManagerService $otpManager,
    ) {}

    public function execute(
        string $identifier,
        string $trackingCode,
        string $otpCode,
        string $password,
        string $guard = 'user'
    ): void {
        $user = $this->getUser($identifier, $guard);

        if (! $user) {
            throw new UserNotFoundException();
        }

        if (! $user->hasSetPassword()) {
            throw new UserDoesNotHavePasswordException;
        }

        if (! $this->otpManager->verify($identifier, $guard, $otpCode, $trackingCode, OtpType::RESET_PASSWORD)) {
            throw new InvalidOtpCode;
        }

        $user->password = Hash::make($password);
        $user->save();
    }
}
