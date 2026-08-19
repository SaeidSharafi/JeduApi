<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\UserBannedException;
use App\Exceptions\UserNotFoundException;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

final class PasswordLoginAction extends AuthAction
{
    public function __construct(
        protected AuthenticateUserAction $authenticateUser
    ) {}

    public function execute(null|User|Staff|string $identifier, string $type, string $password, string $guard = 'user'): NewAccessToken
    {

        if ($identifier && is_string($identifier)) {
            $identifier = $this->getUser($identifier, $guard);
        }

        if (! $identifier) {
            throw new UserNotFoundException();
        }

        if (($identifier instanceof User || $identifier instanceof Staff) && $identifier->is_banned) {
            throw new UserBannedException();
        }

        if (! $identifier->hasSetPassword() || ! Hash::check($password, $identifier->password)) {
            throw ValidationException::withMessages([
                'password' => [__('messages.auth.login.invalid_credentials')],
            ]);
        }

        return $this->authenticateUser->execute($identifier, $guard);
    }
}
