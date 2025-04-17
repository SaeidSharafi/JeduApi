<?php

namespace App\Actions\Auth;

use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use App\Exceptions\UserHasPasswordException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;

class InitiateAuthAction extends AuthAction
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
        protected AuthenticateUserAction $authenticateUser,
    ) {}

    public function execute(string $identifier, string $guard = 'user'): SentOtpDto
    {
        $user = $this->getUser($identifier, $guard);

        if (! $user) {
            if ($guard === 'admin' || $this->getIndetifierType($identifier) === 'email') {
                throw new UserNotFoundException;
            }
            $user = User::create(
                [
                    'phone' => $identifier,
                ]
            );

            return $this->generateOtp->execute($user, OtpType::SIGNUP);
        }

        if ($user->hasSetPassword()) {
            throw new UserHasPasswordException;
        }

        return $this->generateOtp->execute($user, OtpType::SIGNIN);
    }
}
