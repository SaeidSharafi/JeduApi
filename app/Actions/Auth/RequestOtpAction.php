<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\OtpManager\SentOtpDto;
use App\Enums\System\OtpType;
use App\Exceptions\UserNotFoundException;

final class RequestOtpAction extends AuthAction
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
