<?php

namespace App\Actions\Api\V1\Auth;

use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;

class ForgotPasswordAction extends AuthAction
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
    ) {}

    public function execute(string $identifier, string $guard = 'user'): SentOtpDto
    {
        $user = $this->getUser($identifier, $guard);

        if (! $user) {
            throw new UserNotFoundException();
        }

        if ($user->hasSetPassword()) {
            return $this->generateOtp->execute($user, OtpType::RESET_PASSWORD);
        }

        throw new UserDoesNotHavePasswordException();
    }
}
