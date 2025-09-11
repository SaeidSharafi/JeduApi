<?php

declare(strict_types=1);

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\GenerateOtpAction;
use App\Actions\Auth\InitiateAuthAction;
use App\Actions\Auth\PasswordLoginAction;
use App\Data\OtpManager\SentOtpDto;
use App\Enums\OtpType;
use App\Exceptions\UserHasPasswordException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

beforeEach(function (): void {
    Notification::fake();
});

test('InitiateAuthAction returns correct action for user without password', function (): void {
    $mockGenerateOtp      = Mockery::mock(GenerateOtpAction::class);
    $mockAuthenticateUser = Mockery::mock(AuthenticateUserAction::class);

    app()->instance(GenerateOtpAction::class, $mockGenerateOtp);
    app()->instance(AuthenticateUserAction::class, $mockAuthenticateUser);

    $action = app()->make(InitiateAuthAction::class);
    $user   = User::factory()->create(['password' => null])->fresh();

    $placeholderCode         = 123456;
    $placeholderTrackingCode = 'mock-uuid-test-code';
    $placeholderWaitingTime  = 60;

    $mockGenerateOtp->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(function (User $argumentUser) use ($user) {
                return $argumentUser instanceof User && $argumentUser->is($user);
            }),
            OtpType::SIGNIN
        )
        ->andReturn(
            new SentOtpDto(
                code: $placeholderCode,
                otpType: OtpType::SIGNIN,
                waitingTime: $placeholderWaitingTime,
                trackingCode: $placeholderTrackingCode
            )
        );

    $result = $action->execute($user->email);

    expect($result)->toBeInstanceOf(SentOtpDto::class)
        ->and($result->otpType)->toBe(OtpType::SIGNIN)
        ->and($result->code)->toBe($placeholderCode)
        ->and($result->trackingCode)->toBe($placeholderTrackingCode);
});

test('InitiateAuthAction returns correct action for user with password', function (): void {
    $mockGenerateOtp      = Mockery::mock(GenerateOtpAction::class);
    $mockAuthenticateUser = Mockery::mock(AuthenticateUserAction::class);
    app()->instance(GenerateOtpAction::class, $mockGenerateOtp);
    app()->instance(AuthenticateUserAction::class, $mockAuthenticateUser);
    $action = app()->make(InitiateAuthAction::class);
    $user   = User::factory()->create(['password' => Hash::make('password')]);
    // Should throw UserHasPasswordException
    expect(fn () => $action->execute($user->email, 'email'))
        ->toThrow(UserHasPasswordException::class);
});

test('PasswordLoginAction authenticates valid credentials', function (): void {
    $mockAuthenticateUser = Mockery::mock(AuthenticateUserAction::class);
    $mockToken            = Mockery::mock(NewAccessToken::class);
    $mockAuthenticateUser->shouldReceive('execute')->andReturn($mockToken);
    $action   = new PasswordLoginAction($mockAuthenticateUser);
    $password = 'password123';
    $user     = User::factory()->create([
        'password' => Hash::make($password),
    ]);
    $result = $action->execute($user->email, 'email', $password);
    expect($result)->toBe($mockToken);
});

test('PasswordLoginAction rejects invalid credentials', function (): void {
    $mockAuthenticateUser = Mockery::mock(AuthenticateUserAction::class);
    $action               = new PasswordLoginAction($mockAuthenticateUser);
    $user                 = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    expect(fn (): NewAccessToken => $action->execute($user->email, 'email', 'wrong-password'))
        ->toThrow(ValidationException::class, __('messages.auth.login.invalid_credentials'));
});

test('PasswordLoginAction handles missing user', function (): void {
    $mockAuthenticateUser = Mockery::mock(AuthenticateUserAction::class);
    $action               = new PasswordLoginAction($mockAuthenticateUser);

    expect(fn (): NewAccessToken => $action->execute('nonexistent@example.com', 'email', 'password123'))
        ->toThrow(App\Exceptions\UserNotFoundException::class);
});
