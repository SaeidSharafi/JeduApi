<?php

namespace App\Actions\Api\V1\Auth;

use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use App\Exceptions\UserNotFoundException;

class RequestOtpAction extends AuthAction
{
    public function __construct(
        protected GenerateOtpAction $generateOtp
    ) {}

    public function execute(string $identifier, OtpType $otpType, string $guard = 'user'): SentOtpDto
    {

        $user = $this->getUser($identifier, $guard);

        if (! $user) {
            throw new UserNotFoundException;
        }

        return $this->generateOtp->execute($user, $otpType);
    }
}
