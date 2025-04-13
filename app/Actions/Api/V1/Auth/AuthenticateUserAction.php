<?php

namespace App\Actions\Api\V1\Auth;

use Illuminate\Database\Eloquent\Model;

class AuthenticateUserAction
{
    public function execute(Model $user, string $guard = 'user'): array
    {
        $tokenName = $guard === 'admin' ? 'admin_token' : 'auth_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            $guard => $user
        ];
    }
}
