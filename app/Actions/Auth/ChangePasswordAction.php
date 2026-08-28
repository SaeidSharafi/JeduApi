<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ChagePasswordData;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePasswordAction
{
    public function handle(Staff|User $user, ChagePasswordData $data): void
    {
        $user->refresh();

        if (! $data->current_password && $user->password) {
            throw ValidationException::withMessages([
                'current_password' => __('validation.password.current_password_required'),
            ]);
        }

        if ($data->current_password && ! password_verify($data->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('validation.password.current_password_does_not_match'),
            ]);
        }

        $user->password = Hash::make($data->password);
        $user->save();

    }
}
