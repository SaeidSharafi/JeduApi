<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\AuthInitiationResultData;
use App\Enums\System\OtpType;
use App\Exceptions\UserNotFoundException;
use App\Helpers\PhoneNumberHelper;
use App\Models\User;
use App\Services\Auth\RegistrationVelocityService;
use Illuminate\Database\QueryException;

final class InitiateAuthAction extends AuthAction
{
    public function __construct(
        protected GenerateOtpAction $generateOtp,
        protected AuthenticateUserAction $authenticateUser,
        protected RegistrationVelocityService $registrationVelocity,
    ) {}

    public function execute(
        string $identifier,
        string $guard = 'user',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuthInitiationResultData {
        $user = $this->getUser($identifier, $guard);

        if (! $user) {
            if ($guard === 'staff' || $this->getIdentifierType($identifier) === 'email') {
                throw new UserNotFoundException;
            }

            $user = User::query()
                ->whereIn('phone', PhoneNumberHelper::lookupVariants($identifier))
                ->first();

            if (! $user) {
                $deviceHash = $this->registrationVelocity->fingerprint($ipAddress, $userAgent);
                $this->registrationVelocity->assertWithinLimits($ipAddress, $deviceHash);

                $created = false;
                try {
                    $user    = User::query()->create(['phone' => PhoneNumberHelper::normalize($identifier)]);
                    $created = true;
                } catch (QueryException) {
                    $user = User::query()->whereIn('phone', PhoneNumberHelper::lookupVariants($identifier))->firstOrFail();
                }

                if ($created) {
                    $this->registrationVelocity->record($user, $ipAddress, $userAgent);
                }
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
