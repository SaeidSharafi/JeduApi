<?php

declare(strict_types=1);

use App\Enums\System\OtpType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

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
    if ($fakeGenerator instanceof Tests\Support\Fakes\FakeOtpGenerator) {
        $fakeGenerator->setNextOtpCode($this->otpCode)
            ->setNextTrackingCode($this->trackingCode);
    }
});

test('successful signup otp verification marks the phone as verified', function (): void {
    $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '09301234567',
    ])->assertOk();

    $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => '09301234567',
        'type'          => 'phone',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNUP->value,
    ])->assertOk();

    $user = User::query()->where('phone', '09301234567')->first();

    expect($user)->not->toBeNull()
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->phone_verified_at)->toBeInstanceOf(CarbonInterface::class);
});

test('signin otp verification does not mark the phone as verified', function (): void {
    $user = User::factory()->create([
        'phone'             => '09301234567',
        'phone_verified_at' => null,
    ]);

    $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '09301234567',
    ])->assertOk();

    $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => '09301234567',
        'type'          => 'phone',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ])->assertOk();

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('registration stores the phone number with a leading zero', function (): void {
    $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '9351234567',
    ])->assertOk();

    $this->assertDatabaseHas('users', [
        'phone' => '09351234567',
    ]);
});

test('auth lookup treats numbers with and without the leading zero as identical', function (): void {
    User::factory()->create(['phone' => '09351234567']);

    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '9351234567',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'otp_type'     => OtpType::SIGNIN->value,
                'identifier'   => '9351234567',
                'login_method' => 'OTP',
            ],
        ]);

    expect(User::query()->where('phone', '09351234567')->count())->toBe(1);
});

test('auth lookup finds a stored phone that lacks the leading zero', function (): void {
    $user = User::factory()->create(['phone' => '09351234567']);

    // Simulate a legacy row inserted directly into the database.
    DB::table('users')->where('id', $user->id)->update(['phone' => '9351234567']);

    $response = $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '09351234567',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'otp_type'     => OtpType::SIGNIN->value,
                'identifier'   => '09351234567',
                'login_method' => 'OTP',
            ],
        ]);

    expect(User::query()->where('phone', '9351234567')->count())->toBe(1);
});

test('otp verification succeeds when the identifier lacks the leading zero', function (): void {
    User::factory()->create(['phone' => '09351234567']);

    $this->postJson(route('api.v1.auth.initiate'), [
        'identifier' => '9351234567',
    ])->assertOk();

    $this->postJson(route('api.v1.auth.otp-verify'), [
        'identifier'    => '9351234567',
        'type'          => 'phone',
        'otp_code'      => $this->otpCode,
        'tracking_code' => $this->trackingCode,
        'otp_type'      => OtpType::SIGNIN->value,
    ])->assertOk();
});
