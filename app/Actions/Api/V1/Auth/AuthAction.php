<?php

namespace App\Actions\Api\V1\Auth;

use App\Models\Admin;
use App\Models\User;

abstract class AuthAction
{
    public function getModel(string $guard): string
    {
        return $guard === 'admin' ? Admin::class : User::class;
    }

    public function getIndetifierType(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    public function getUser($identifier, $guard): User|Admin|null
    {
        $model = $this->getModel($guard);

        return $model::when(
            $this->getIndetifierType($identifier) === 'email',
            fn ($q) => $q->where('email', $identifier),
            fn ($q) => $q->where('phone', $identifier)
        )->first();
    }
}
