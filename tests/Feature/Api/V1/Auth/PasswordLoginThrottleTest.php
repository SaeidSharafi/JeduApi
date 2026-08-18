<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\PasswordLoginThrottleService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * @var $this TestCase
 */
beforeEach(function (): void {
    $this->throttle = app(PasswordLoginThrottleService::class);

    $this->user = User::factory()->create([
        'email'    => 'throttle@example.com',
        'password' => Hash::make('password-123'),
    ]);
});

test('repeated failed password logins return 429 after the baseline limit', function (): void {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login/password', [
            'identifier' => 'throttle@example.com',
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(422);
    }

    $response = $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))->toBeLessThanOrEqual(60);
});

test('lockout window escalates to 15 minutes after 10 consecutive failures', function (): void {
    $failuresKey = $this->throttle->idFailuresKey('shop', 'throttle@example.com');

    foreach (range(1, 9) as $i) {
        RateLimiter::hit($failuresKey, config('password_throttle.shop.failure_counter_ttl_seconds'));
    }

    // at tier 2 only one attempt per window is allowed, so the next failure
    // fills the window and the following attempt is locked for ~15 minutes
    $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ])->assertStatus(422);

    $response = $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThanOrEqual(840);
});

test('lockout window escalates to one hour after 15 consecutive failures', function (): void {
    $failuresKey = $this->throttle->idFailuresKey('shop', 'throttle@example.com');

    foreach (range(1, 14) as $i) {
        RateLimiter::hit($failuresKey, config('password_throttle.shop.failure_counter_ttl_seconds'));
    }

    $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ])->assertStatus(422);

    $response = $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThanOrEqual(3540);
});

test('lockout escalation is driven by consecutive failures, not burst rate', function (): void {
    // one spaced-out failure per window: the window is reset before every
    // attempt so the baseline is never exceeded, but failures accumulate
    foreach (range(1, 10) as $i) {
        RateLimiter::clear($this->throttle->idWindowKey('shop', 'throttle@example.com'));
        RateLimiter::clear($this->throttle->ipWindowKey('shop', '127.0.0.1'));

        $this->postJson('/api/v1/auth/login/password', [
            'identifier' => 'throttle@example.com',
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(422);
    }

    // 10 consecutive failures cross into the 15-minute tier; the window from
    // the last failure is still full, so the next attempt is locked ~15 min
    $response = $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThanOrEqual(840);
});

test('a successful login resets the failure counter', function (): void {
    $failuresKey = $this->throttle->idFailuresKey('shop', 'throttle@example.com');
    $windowKey   = $this->throttle->idWindowKey('shop', 'throttle@example.com');

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/v1/auth/login/password', [
            'identifier' => 'throttle@example.com',
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(422);
    }

    expect(RateLimiter::attempts($failuresKey))->toBe(3);

    $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'password-123',
    ])->assertOk();

    expect(RateLimiter::attempts($failuresKey))->toBe(0)
        ->and(RateLimiter::attempts($windowKey))->toBe(0);

    // baseline is restored after a success: five more failures, then a 429
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login/password', [
            'identifier' => 'throttle@example.com',
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ])->assertStatus(429);
});

test('password login is throttled per ip across different accounts', function (): void {
    foreach (range(1, 5) as $i) {
        User::factory()->create([
            'email'    => "spray{$i}@example.com",
            'password' => Hash::make('password-123'),
        ]);

        $this->postJson('/api/v1/auth/login/password', [
            'identifier' => "spray{$i}@example.com",
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(422);
    }

    // a sixth attempt from the same IP is blocked even though the account is new
    $this->postJson('/api/v1/auth/login/password', [
        'identifier' => 'throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ])->assertStatus(429);
});

test('password login is throttled per identifier across different ips', function (): void {
    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
            ->postJson('/api/v1/auth/login/password', [
                'identifier' => 'throttle@example.com',
                'type'       => 'email',
                'password'   => 'wrong-password',
            ])->assertStatus(422);
    }

    // the identifier key is global: a new IP cannot bypass the accumulated failures
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->postJson('/api/v1/auth/login/password', [
            'identifier' => 'throttle@example.com',
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(429);
});
