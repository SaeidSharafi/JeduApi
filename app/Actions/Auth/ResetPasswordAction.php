<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\System\OtpType;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;
use App\Services\OtpManagerService;
use Illuminate\Support\Facades\Hash;

final class ResetPasswordAction extends AuthAction
{
    public function __construct(
        protected OtpManagerService $otpManager,
        protected VerifyOtpAction $verifyOtpAction
    ) {}

    public function execute(
        string $identifier,
        string $trackingCode,
        int $otpCode,
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
        } catch (InvalidOtpCodeException $e) {
            throw new InvalidOtpCodeException();
        }

        $user->password = Hash::make($password);
        $user->save();
    }
}
