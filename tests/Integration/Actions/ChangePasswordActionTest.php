<?php

use App\Actions\Auth\ChangePasswordAction;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('change password validation checks', function () {
    $user = User::factory()->create(
        [
            'password' => Hash::make('password')
        ]
    );

    $action = new ChangePasswordAction();
    $request =  new ChangePasswordRequest([
        'current_password' => 'wrongpassword',
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);
   expect(fn() => $action->handle($user, $request))->toThrow(
       ValidationException::class, __('validation.password.current_password_does_not_match')
   );

    $request =  new ChangePasswordRequest([
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);
    expect(fn() => $action->handle($user, $request))->toThrow(
        ValidationException::class, __('validation.password.current_password_required')
    );

});

it('change password', function () {
    $user = User::factory()->create(
        [
            'password' => Hash::make('password')
        ]
    );

    $action = new ChangePasswordAction();
    $action->handle($user, new ChangePasswordRequest([
        'current_password' => 'password',
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]));

    $user->refresh();
    expect(Hash::check('newpassword', $user->password))->toBeTrue();
});

it('change password when user does not have password', function () {
    $user = User::factory()->create(
        [
            'password' => null
        ]
    );

    $action = new ChangePasswordAction();
    $action->handle($user, new ChangePasswordRequest([
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]));

    $user->refresh();
    expect(Hash::check('newpassword', $user->password))->toBeTrue();
});
