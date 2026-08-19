<?php

declare(strict_types=1);

use App\Models\User;
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

test('fourth registration from the same IP is rejected', function (): void {
    $phones = ['09301234501', '09301234502', '09301234503'];

    foreach ($phones as $phone) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson(route('api.v1.auth.initiate'), ['identifier' => $phone])
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => '09301234504'])
        ->assertStatus(429)
        ->assertJson(['message' => __('messages.auth.register.throttled')]);
});

test('per-IP cap is independent of the device hash', function (): void {
    // Rotating user agents => every request has a distinct device hash, so
    // only the per-IP cap can reject the fourth registration.
    $uas = ['Mozilla/5.0 (Agent A)', 'Mozilla/5.0 (Agent B)', 'Mozilla/5.0 (Agent C)'];

    foreach ($uas as $index => $ua) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson(route('api.v1.auth.initiate'), ['identifier' => '0930123456'.$index], ['User-Agent' => $ua])
            ->assertOk();
    }

    // Exactly 3 registrations from the same IP succeed.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => '09301234563'], ['User-Agent' => 'Mozilla/5.0 (Agent D)'])
        ->assertStatus(429)
        ->assertJson(['message' => __('messages.auth.register.throttled')]);
});

test('fourth registration from the same device hash is rejected', function (): void {
    $ua     = ['User-Agent' => 'Mozilla/5.0 (Test Device)'];
    $phones = ['09301234511', '09301234512', '09301234513'];

    foreach ($phones as $phone) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson(route('api.v1.auth.initiate'), ['identifier' => $phone], $ua)
            ->assertOk();
    }

    // Same server-side fingerprint (ip + user_agent) => a fourth registration
    // from the same device is rejected.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => '09301234514'], $ua)
        ->assertStatus(429)
        ->assertJson(['message' => __('messages.auth.register.throttled')]);

    // A genuinely different device (different ip, hence different hash) is not
    // blocked even when it shares the user agent.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => '09301234515'], $ua)
        ->assertOk();
});

test('registrations within the limit succeed', function (): void {
    $ua = ['User-Agent' => 'Mozilla/5.0 (Test Device)'];

    foreach (['09301234521', '09301234522'] as $phone) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson(route('api.v1.auth.initiate'), ['identifier' => $phone], $ua)
            ->assertOk();
    }

    $this->assertDatabaseHas('users', ['phone' => '09301234521']);
    $this->assertDatabaseHas('users', ['phone' => '09301234522']);
});

test('registration records the device fingerprint', function (): void {
    $ip = '10.0.0.1';
    $ua = 'Mozilla/5.0 (Test Device)';

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => '09301234531'], ['User-Agent' => $ua])
        ->assertOk();

    $userId = User::query()->where('phone', '09301234531')->firstOrFail()->id;

    $this->assertDatabaseHas('user_devices', [
        'ip_address'  => $ip,
        'user_agent'  => $ua,
        'user_id'     => $userId,
        'device_hash' => hash('sha256', $ip.$ua),
    ]);
});

test('existing users can still initiate auth after the cap is reached', function (): void {
    $phones = ['09301234541', '09301234542', '09301234543'];

    foreach ($phones as $phone) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson(route('api.v1.auth.initiate'), ['identifier' => $phone])
            ->assertOk();
    }

    // The cap is reached: a fourth NEW registration from the same IP is rejected.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => '09301234544'])
        ->assertStatus(429);

    User::factory()->create(['phone' => '09901112233']);

    // An existing user is a SIGNIN, not a registration, so it is not blocked.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => '09901112233'])
        ->assertOk();
});

test('registrations from the previous day do not count toward the daily cap', function (): void {
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        DB::table('user_devices')->insert([
            'user_id'     => $user->id,
            'device_hash' => hash('sha256', '10.0.0.1'),
            'user_agent'  => null,
            'ip_address'  => '10.0.0.1',
            'created_at'  => now()->subDay(),
            'updated_at'  => now()->subDay(),
        ]);
    }

    foreach (['09301234551', '09301234552', '09301234553'] as $phone) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson(route('api.v1.auth.initiate'), ['identifier' => $phone])
            ->assertOk();
    }
});

test('email registration is still blocked for new users', function (): void {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('api.v1.auth.initiate'), ['identifier' => 'new-user@example.com'])
        ->assertStatus(404);
});
