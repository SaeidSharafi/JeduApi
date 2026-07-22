<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePasswordAction
{

    public function handle(Staff|User $user, ChangePasswordRequest $request): void
    {
        if (!$request->current_password && $user->password) {
            throw ValidationException::withMessages([
                'current_password' => __('validation.password.current_password_required'),
            ]);
        }

        if ($request->current_password && !password_verify($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('validation.password.current_password_does_not_match'),
            ]);
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

    }
}
