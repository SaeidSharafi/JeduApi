<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\OtpManager\SentOtpDto;
use App\Enums\System\OtpType;
use App\Exceptions\UserHasPasswordException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;

final class InitiateAuthAction extends AuthAction
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
        protected AuthenticateUserAction $authenticateUser,
    ) {}

    public function execute(string $identifier, string $guard = 'user'): SentOtpDto
    {
        $user = $this->getUser($identifier, $guard);

        if (! $user) {
            if ($guard === 'staff' || $this->getIdentifierType($identifier) === 'email') {
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
