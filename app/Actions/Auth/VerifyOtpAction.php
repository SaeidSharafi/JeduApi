<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\System\OtpType;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\UserNotFoundException;
use App\Models\Staff;
use App\Models\User;
use App\Services\OtpManagerService;

final class VerifyOtpAction extends AuthAction
{
    public function __construct(
        protected OtpManagerService $otpManagerService,
        protected AuthenticateUserAction $authenticateUserAction
    ) {}

    public function execute(string|Staff|User $identifier, string $trackingCode, int $otpCode, OtpType $otpType, string $guard = 'user'): Staff|User
    {
        $user = $identifier;
        if (is_string($identifier)) {
            $user = $this->getUser($identifier, $guard);
        }

        if (! $user) {
            throw new UserNotFoundException();
        }
        if (! $this->otpManagerService->verify($user->phone, $guard, $otpCode, $trackingCode, $otpType)) {
            throw new InvalidOtpCodeException();
        }

        return $user;

    }
}
