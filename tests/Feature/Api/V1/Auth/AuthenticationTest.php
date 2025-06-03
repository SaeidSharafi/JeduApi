<?php

declare(strict_types=1);

use App\Data\OtpManager\OtpDto;
use App\Enums\OtpType;
use App\Models\User;
use App\Notifications\Auth\OtpEmailNotification;
use App\Notifications\Auth\OtpSmsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @var $this TestCase
 */
beforeEach(function (): void {
    Notification::fake();
    $minOtpCode         = config('otp.code_min');
    $maxOtpCode         = config('otp.code_max');
    $this->otpCode      = random_int($minOtpCode, $maxOtpCode);
    $this->trackingCode = 'test-tracking';

    $fakeGenerator = $this->app->make(App\Contracts\OtpGeneratorInterface::class);
    if ($fakeGenerator instanceof Tests\Fakes\FakeOtpGenerator) {
        $fakeGenerator->setNextOtpCode($this->otpCode)
            ->setNextTrackingCode($this->trackingCode);
    }

});
test('new user can initiate authentication with phone', function (): void {
    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '09301234567',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'OTP sent successfully',
            'data'    => [
                'tracking_code' => $this->trackingCode,
                'otp_type'      => OtpType::SIGNUP->value,
                'identifier'    => '09301234567',
                'login_method'  => 'OTP',
            ],
            'metadata' => [],
        ]);
});
test('new user can not initiate authentication with email', function (): void {
    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => 'test@example.com',
    ]);

    $response->assertNotFound();
});
test('user can initiate authentication with email', function (): void {
    $user = User::factory()->create([
        'email'    => 'test@example.com',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => 'test@example.com',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'OTP sent successfully',
            'data'    => [
                'tracking_code' => $this->trackingCode,
                'otp_type'      => 'SIGNIN',
                'identifier'    => 'test@example.com',
                'login_method'  => 'OTP',
            ],
            'metadata' => [],
        ]);
});

test('user can initiate authentication with phone', function (): void {
    $user = User::factory()->create([
        'phone'    => '09301234567',
        'password' => null,
    ]);

    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '09301234567',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'OTP sent successfully',
            'data'    => [
                'tracking_code' => $this->trackingCode,
                'otp_type'      => 'SIGNIN',
                'identifier'    => '09301234567',
                'login_method'  => 'OTP',
            ],
            'metadata' => [],
        ]);
});

test('user with password gets password login action', function (): void {
    $user = User::factory()->create([
        'email'    => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => 'test@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'User has set password',
            'data'    => [
                'login_method' => 'PASSWORD',
            ],
            'metadata' => [],
        ]);
});

test('non existent user con not register with email', function (): void {
    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => 'nonexistent@example.com',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'User not found',
        ]);
});

test('user can request resend otp', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson(route('api.v1.auth.otp-resend'), [
        'identifier' => 'test@example.com',
        'otp_type'   => OtpType::SIGNIN->value,
    ]);
    $response->assertOk()
        ->assertJson([
            'message' => 'OTP resent successfully',
            'data'    => [
                'tracking_code' => $this->trackingCode,
                'otp_type'      => 'SIGNIN',
                'identifier'    => 'test@example.com',
                'login_method'  => 'OTP',
            ],
            'metadata' => [],
        ]);

    Notification::assertSentTo($user, OtpEmailNotification::class);
});
test('non existent user can not request otp', function (): void {

    $response = $this->postJson(route('api.v1.auth.otp-resend'), [
        'identifier' => 'test@example.com',
        'otp_type'   => OtpType::SIGNIN->value,
    ]);
    $response->assertNotFound();

    Notification::assertNothingSent();
});
test('user can request otp with phone', function (): void {
    $user = User::factory()->create(['phone' => '09301234567']);

    $response = $this->postJson(route('api.v1.auth.otp-resend'), [
        'identifier' => '09301234567',
        'type'       => 'phone',
        'otp_type'   => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'OTP resent successfully',
            'data'    => [
                'tracking_code' => $this->trackingCode,
                'otp_type'      => 'SIGNIN',
                'identifier'    => '09301234567',
                'login_method'  => 'OTP',
            ],
            'metadata' => [],
        ]);

    Notification::assertSentTo($user, OtpSmsNotification::class);
});

test('user can verify otp and login', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => 'test@example.com',
    ]);

    $response = $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => 'test@example.com',
        'type'          => 'email',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});

test('user can verify otp and login with phone', function (): void {
    $user         = User::factory()->create(['phone' => '09301234567']);
    $trackingCode = 'test-tracking';

    Cache::put('otp_09301234567_user_value_SIGNIN', new OtpDto($this->otpCode, $trackingCode), 300);

    $response = $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => '09301234567',
        'type'          => 'phone',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});

test('user can login with password', function (): void {
    $user = User::factory()->create([
        'email'    => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson(route('api.v1.auth.password-login'), [
        'identifier' => 'test@example.com',
        'type'       => 'email',
        'password'   => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});
test('non existent user can not login with password', function (): void {
    $response = $this->postJson(route('api.v1.auth.password-login'), [
        'identifier' => 'test@example.com',
        'password'   => 'password123',
    ]);

    $response->assertNotFound();
});
test('user can login with phone and password', function (): void {
    $user = User::factory()->create([
        'phone'    => '09301234567',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson(route('api.v1.auth.password-login'), [
        'identifier' => '09301234567',
        'type'       => 'phone',
        'password'   => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user',
            ],
        ]);
});
test('non existent user can not verify otp', function (): void {

    $response = $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => 'test@example.com',
        'type'          => 'email',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ]);

    $response->assertNotFound();
});
test('authenticated user can logout', function (): void {
    $user  = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.v1.auth.logout'));

    $response->assertStatus(204);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
