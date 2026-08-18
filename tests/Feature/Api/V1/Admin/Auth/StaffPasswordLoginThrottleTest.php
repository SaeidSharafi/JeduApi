<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Services\Auth\PasswordLoginThrottleService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * @var $this TestCase
 */
beforeEach(function (): void {
    $this->throttle = app(PasswordLoginThrottleService::class);

    $this->staff = Staff::factory()->create([
        'email'    => 'staff-throttle@example.com',
        'password' => Hash::make('password-123'),
    ]);
});

test('staff password login is throttled after 3 failed attempts', function (): void {
    foreach (range(1, 3) as $i) {
        $this->postJson('/api/v1/admin/auth/login/password', [
            'identifier' => 'staff-throttle@example.com',
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(422);
    }

    $response = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'staff-throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))->toBeLessThanOrEqual(60);
});

test('staff lockout window escalates to 15 minutes after 10 consecutive failures', function (): void {
    $failuresKey = $this->throttle->idFailuresKey('staff', 'staff-throttle@example.com');

    foreach (range(1, 9) as $i) {
        RateLimiter::hit($failuresKey, config('password_throttle.staff.failure_counter_ttl_seconds'));
    }

    // at tier 2 only one attempt per window is allowed, so the next failure
    // fills the window and the following attempt is locked for ~15 minutes
    $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'staff-throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ])->assertStatus(422);

    $response = $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'staff-throttle@example.com',
        'type'       => 'email',
        'password'   => 'wrong-password',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThanOrEqual(840);
});

test('a successful staff login resets the failure counter', function (): void {
    $failuresKey = $this->throttle->idFailuresKey('staff', 'staff-throttle@example.com');

    foreach (range(1, 2) as $i) {
        $this->postJson('/api/v1/admin/auth/login/password', [
            'identifier' => 'staff-throttle@example.com',
            'type'       => 'email',
            'password'   => 'wrong-password',
        ])->assertStatus(422);
    }

    expect(RateLimiter::attempts($failuresKey))->toBe(2);

    $this->postJson('/api/v1/admin/auth/login/password', [
        'identifier' => 'staff-throttle@example.com',
        'type'       => 'email',
        'password'   => 'password-123',
    ])->assertOk();

    expect(RateLimiter::attempts($failuresKey))->toBe(0);
});
