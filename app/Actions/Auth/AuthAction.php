<?php

namespace App\Actions\Auth;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

    public function getUser(string $identifier, string $guard): User|Admin|null
    {
        $model = $this->getModel($guard);

        return $model::when(
            $this->getIndetifierType($identifier) === 'email',
            fn(Builder $q) => $q->where('email', $identifier),
            fn(Builder $q) => $q->where('phone', $identifier)
        )->first();
    }
}
