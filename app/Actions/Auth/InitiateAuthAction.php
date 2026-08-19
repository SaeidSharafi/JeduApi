<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\AuthInitiationResultData;
use App\Enums\System\OtpType;
use App\Exceptions\UserNotFoundException;
use App\Helpers\PhoneNumberHelper;
use App\Models\User;
use Illuminate\Database\QueryException;

final class InitiateAuthAction extends AuthAction
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
        protected AuthenticateUserAction $authenticateUser,
    ) {}

    public function execute(string $identifier, string $guard = 'user'): AuthInitiationResultData
    {
        $user = $this->getUser($identifier, $guard);

        if (! $user) {
            if ($guard === 'staff' || $this->getIdentifierType($identifier) === 'email') {
                throw new UserNotFoundException;
            }
            try {
                $user = User::query()
                    ->whereIn('phone', PhoneNumberHelper::lookupVariants($identifier))
                    ->first()
                    ?? User::query()->create(['phone' => PhoneNumberHelper::normalize($identifier)]);
            } catch (QueryException) {
                $user = User::query()->whereIn('phone', PhoneNumberHelper::lookupVariants($identifier))->firstOrFail();
            }

            return AuthInitiationResultData::otp(
                $this->generateOtp->execute($user, OtpType::SIGNUP)
            );
        }

        if ($user->hasSetPassword()) {
            return AuthInitiationResultData::password();
        }

        return AuthInitiationResultData::otp(
            $this->generateOtp->execute($user, OtpType::SIGNIN)
        );
    }
}
