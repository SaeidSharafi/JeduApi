<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\System\OtpType;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\UserBannedException;
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
        // Banned customers are blocked from OTP login, but may still reset
        // their password (RESET_PASSWORD) so an unban is not a dead end.
        if ($guard === 'user' && $otpType !== OtpType::RESET_PASSWORD && $user->is_banned) {
            throw new UserBannedException();
        }
        if (! $this->otpManagerService->verify($user->phone, $guard, $otpCode, $trackingCode, $otpType)) {
            throw new InvalidOtpCodeException();
        }

        return $user;

    }
}
