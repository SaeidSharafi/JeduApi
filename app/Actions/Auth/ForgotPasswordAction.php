<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\OtpManager\SentOtpDto;
use App\Enums\System\OtpType;
use App\Exceptions\UserDoesNotHavePasswordException;
use App\Exceptions\UserNotFoundException;

final class ForgotPasswordAction extends AuthAction
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
