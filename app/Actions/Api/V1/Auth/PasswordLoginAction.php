<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordLoginAction
{
    public function __construct(
        protected AuthenticateUserAction $authenticateUser
    ) {
    }

    public function execute(string $identifier, string $type, string $password, string $guard = 'user'): array
    {
        $model = $guard === 'admin' ? Admin::class : User::class;

        $user = $model::when(
            $type === 'email',
            fn ($q) => $q->where('email', $identifier),
            fn ($q) => $q->where('phone', $identifier)
        )->firstOrFail();

        if (!$user->hasSetPassword() || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->authenticateUser->execute($user, $guard);
    }
}
