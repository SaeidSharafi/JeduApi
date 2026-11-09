<?php

declare(strict_types=1);

use App\Actions\Auth\ChangePasswordAction;
use App\Data\Auth\ChagePasswordData;
use App\Models\User;
use Illuminate\Validation\ValidationException;

mutates(ChangePasswordAction::class);

it('change password validation checks', function (): void {
    $user = User::factory()->create(
        [
            'password' => Hash::make('password'),
        ]
    );

    $action  = new ChangePasswordAction();
    $request = ChagePasswordData::from([
        'current_password'      => 'wrongpassword',
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);
    expect(fn () => $action->handle($user, $request))->toThrow(
        ValidationException::class, __('validation.password.current_password_does_not_match')
    );

    $request = ChagePasswordData::from([
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]);
    expect(fn () => $action->handle($user, $request))->toThrow(
        ValidationException::class, __('validation.password.current_password_required')
    );

});

it('change password', function (): void {
    $user = User::factory()->create(
        [
            'password' => Hash::make('password'),
        ]
    );

    $action = new ChangePasswordAction();
    $action->handle($user, ChagePasswordData::from([
        'current_password'      => 'password',
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]));

    $user->refresh();
    expect(Hash::check('newpassword', $user->password))->toBeTrue();
});

it('change password when user does not have password', function (): void {
    $user = User::factory()->create(
        [
            'password' => null,
        ]
    );

    $action = new ChangePasswordAction();
    $action->handle($user, ChagePasswordData::from([
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]));

    $user->refresh();
    expect(Hash::check('newpassword', $user->password))->toBeTrue();
});

it('change password when user does not have password and sends current password', function (): void {
    $user = User::factory()->create(
        [
            'password' => null,
        ]
    );

    $action = new ChangePasswordAction();
    $action->handle($user, ChagePasswordData::from([
        'current_password'      => 'anything',
        'password'              => 'newpassword',
        'password_confirmation' => 'newpassword',
    ]));

    $user->refresh();
    expect(Hash::check('newpassword', $user->password))->toBeTrue();
});
