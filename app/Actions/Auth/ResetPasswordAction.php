<?php

namespace App\Actions\Auth;

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
        protected VerifyOtpAction $verifyOtpAction
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
            throw new UserDoesNotHavePasswordException();
        }

        try {
           $this->verifyOtpAction->execute($user, $trackingCode, $otpCode, OtpType::RESET_PASSWORD, $guard);
        }catch (InvalidOtpCode $e){
            throw new InvalidOtpCode();
        }

        $user->password = Hash::make($password);
        $user->save();
    }
}
