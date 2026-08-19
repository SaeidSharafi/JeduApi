<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Helpers\PhoneNumberHelper;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

abstract class AuthAction
{
    final public function getModel(string $guard): string
    {
        return $guard === 'staff' ? Staff::class : User::class;
    }

    final public function getIdentifierType(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    final public function getUser(string $identifier, string $guard): User|Staff|null
    {
        $model = $this->getModel($guard);

        return $model::when(
            $this->getIdentifierType($identifier) === 'email',
            fn (Builder $q) => $q->where('email', $identifier),
            fn (Builder $q) => $q->whereIn('phone', PhoneNumberHelper::lookupVariants($identifier))
        )->first();
    }
}
