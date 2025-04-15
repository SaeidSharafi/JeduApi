<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Actions\Api\V1\Auth\GenerateOtpAction;
use App\Dto\OtpManager\OtpDto;
use App\Dto\OtpManager\SentOtpDto;
use App\Events\OtpPrepared;
use App\Models\User;
use App\Notifications\Api\V1\Auth\OtpEmailNotification;
use App\Notifications\Api\V1\Auth\OtpSmsNotification;
use App\Enums\OtpType;
use App\Services\OtpManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @var $this TestCase
 */
beforeEach(function (): void {
    Notification::fake();
    $minOtpCode = config('otp.code_min');
    $maxOtpCode = config('otp.code_max');
    $this->otpCode = (string) random_int($minOtpCode, $maxOtpCode);
    $this->trackingCode = 'test-tracking';
});

test('user can initiate authentication with email', function (): void {
    $user = User::factory()->create([
        'email'    => 'test@example.com',
        'password' => null,
    ]);
    $mockGenerateOtp = \Mockery::mock(GenerateOtpAction::class);
    app()->instance(GenerateOtpAction::class, $mockGenerateOtp);
    $mockGenerateOtp->shouldReceive('execute')
        ->once()
        ->with(
            \Mockery::on(function (User $argumentUser) use ($user) {
                return $argumentUser instanceof User && $argumentUser->is($user);
            }),
            OtpType::SIGNIN
        )
        ->andReturn(
            new SentOtpDto(
                code: '1234',
                otpType: OtpType::SIGNIN,
                waitingTime: 120,
                trackingCode: "tracking_code_1234"
            )
        );
    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => 'test@example.com',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message'  => 'OTP sent successfully',
            "data"     => [
                "tracking_code" => "tracking_code_1234",
                "otp_type"      => "SIGNIN",
                "identifier"    => "test@example.com",
                "login_method"  => "OTP"
            ],
            "metadata" => []
        ]);
});

test('user can initiate authentication with phone', function (): void {
    $user = User::factory()->create([
        'phone'    => '09301234567',
        'password' => null,
    ]);
    $mockGenerateOtp = \Mockery::mock(GenerateOtpAction::class);
    app()->instance(GenerateOtpAction::class, $mockGenerateOtp);
    $mockGenerateOtp->shouldReceive('execute')
        ->once()
        ->with(
            \Mockery::on(function (User $argumentUser) use ($user) {
                return $argumentUser instanceof User && $argumentUser->is($user);
            }),
            OtpType::SIGNIN
        )
        ->andReturn(
            new SentOtpDto(
                code: '1234',
                otpType: OtpType::SIGNIN,
                waitingTime: 120,
                trackingCode: "tracking_code_1234"
            )
        );
    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '09301234567',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'message'  => 'OTP sent successfully',
            "data"     => [
                "tracking_code" => "tracking_code_1234",
                "otp_type"      => "SIGNIN",
                "identifier"    => "09301234567",
                "login_method"  => "OTP"
            ],
            "metadata" => []
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
            'message'  => 'User has set password',
            'data'     => [
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

    $mockOtpManager = \Mockery::mock(OtpManagerService::class)->makePartial(); // Use makePartial if some methods need real logic, often not needed here
    app()->instance(OtpManagerService::class, $mockOtpManager);

    // 4. Define controlled return value and expected service call
    $expectedIdentifier = $user->phone; // Or email, depending on OtpManagerService logic
    $expectedGuard = 'user';
    $expectedOtpType = OtpType::SIGNIN;
    $controlledTrackingCode = 'tracking_code_resend_1234';
    $controlledCode = 5678; // Example

    $controlledReturnDto = new SentOtpDto(
        code: $controlledCode,
        otpType: $expectedOtpType,
        waitingTime: 120,
        trackingCode: $controlledTrackingCode
    );
    $mockOtpManager->shouldReceive('sendAndRetryCheck')
        ->once()
        ->with($expectedIdentifier, $expectedGuard, $expectedOtpType)
        ->andReturnUsing(function ($identifier, $guard, $type) use ($user, $controlledReturnDto, $controlledCode, $controlledTrackingCode, $expectedOtpType) {
            event(new OtpPrepared(
                indentifier: $identifier,
                guard: $guard,
                code: (string) $controlledCode,
                type: $expectedOtpType,
                trackingCode: $controlledTrackingCode,
                params: [] // Add any params your listener might need
            ));
            return $controlledReturnDto;
        });

    $response = $this->postJson(route('api.v1.auth.otp-resend'), [
        'identifier' => 'test@example.com',
        'otp_type'  => OtpType::SIGNIN->value,
    ]);
    $response->assertOk()
        ->assertJson([
            'message'  => 'OTP resent successfully',
            "data"     => [
                "tracking_code" => $controlledTrackingCode,
                "otp_type"      => "SIGNIN",
                "identifier"    => "test@example.com",
                "login_method"  => "OTP"
            ],
            "metadata" => []
        ]);

    Notification::assertSentTo($user, OtpEmailNotification::class);
});

test('user can request otp with phone', function (): void {
    $user = User::factory()->create(['phone' => '09301234567']);
    $mockOtpManager = \Mockery::mock(OtpManagerService::class)->makePartial(); // Use makePartial if some methods need real logic, often not needed here
    app()->instance(OtpManagerService::class, $mockOtpManager);

    // 4. Define controlled return value and expected service call
    $expectedIdentifier = $user->phone; // Or email, depending on OtpManagerService logic
    $expectedGuard = 'user';
    $expectedOtpType = OtpType::SIGNIN;
    $controlledTrackingCode = 'tracking_code_resend_1234';
    $controlledCode = 5678; // Example

    $controlledReturnDto = new SentOtpDto(
        code: $controlledCode,
        otpType: $expectedOtpType,
        waitingTime: 120,
        trackingCode: $controlledTrackingCode
    );
    $mockOtpManager->shouldReceive('sendAndRetryCheck')
        ->once()
        ->with($expectedIdentifier, $expectedGuard, $expectedOtpType)
        ->andReturnUsing(function ($identifier, $guard, $type) use ($user, $controlledReturnDto, $controlledCode, $controlledTrackingCode, $expectedOtpType) {
            event(new OtpPrepared(
                indentifier: $identifier,
                guard: $guard,
                code: (string) $controlledCode,
                type: $expectedOtpType,
                trackingCode: $controlledTrackingCode,
                params: [] // Add any params your listener might need
            ));
            return $controlledReturnDto;
        });

    $response = $this->postJson(route('api.v1.auth.otp-resend'), [
        'identifier' => '09301234567',
        'type'       => 'phone',
        'otp_type'  => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJson([
            'message'  => 'OTP resent successfully',
            "data"     => [
                "tracking_code" => $controlledTrackingCode,
                "otp_type"      => "SIGNIN",
                "identifier"    => "09301234567",
                "login_method"  => "OTP"
            ],
            "metadata" => []
        ]);

    Notification::assertSentTo($user, OtpSmsNotification::class);
});

test('user can verify otp and login', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $trackingCode = 'test-tracking';

    Cache::put('otp_test@example.com_user_value_SIGNIN', new OtpDto($this->otpCode, $trackingCode), 300);

    $response = $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => 'test@example.com',
        'type'          => 'email',
        'otp_code'  => $this->otpCode,
        'tracking_code' => $trackingCode,
        'otp_type'    => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user'
            ]
        ]);
});

test('user can verify otp and login with phone', function (): void {
    $user = User::factory()->create(['phone' => '09301234567']);
    $trackingCode = 'test-tracking';

    Cache::put('otp_09301234567_user_value_SIGNIN', new OtpDto($this->otpCode, $trackingCode), 300);

    $response = $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => '09301234567',
        'type'          => 'phone',
        'otp_code'  => $this->otpCode,
        'tracking_code' => $trackingCode,
        'otp_type'    => OtpType::SIGNIN->value,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'token',
                'expires_at',
                'type',
                'user'
            ]
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
                'user'
            ]
        ]);
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
                'user'
            ]
        ]);
});

test('authenticated user can logout', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('api.v1.auth.logout'));

    $response->assertStatus(204);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
