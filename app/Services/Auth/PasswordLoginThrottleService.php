<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Progressive-backoff throttle for password login.
 *
 * Two independent dimensions are tracked per guard:
 *  - a global per-identifier key (no IP component) that blocks single-account
 *    stuffing even when the attempts come from many different IPs;
 *  - a per-IP key that blocks distributed spray across many accounts.
 *
 * Each dimension keeps a window counter and a consecutive-failure counter.
 * Below the first tier the window allows the baseline attempts per minute
 * (5/min shop, 3/min staff); once a tier threshold is reached, the window
 * allows a single attempt per lockout window (5 -> 1 min, 10 -> 15 min,
 * 15 -> 1 hour) and the window decay grows accordingly. Because the failure
 * counter is only reset by a successful login, spaced-out attacks escalate
 * exactly like bursts.
 */
final class PasswordLoginThrottleService
{
    /**
     * Allowed attempts per window. Below the first tier this is the baseline
     * (config max_attempts); from the first tier on, one attempt per lockout
     * window so the escalating lockout is enforced even for slow attacks.
     */
    public function maxAttempts(string $guard, int $failures): int
    {
        return $this->tierFor($guard, $failures) === null
            ? (int) ($this->configFor($guard)['max_attempts'] ?? 5)
            : 1;
    }

    public function idWindowKey(string $guard, string $identifier): string
    {
        return "password-login:{$guard}:id:{$identifier}";
    }

    public function ipWindowKey(string $guard, string $ip): string
    {
        return "password-login:{$guard}:ip:{$ip}";
    }

    public function idFailuresKey(string $guard, string $identifier): string
    {
        return "password-login:{$guard}:failures:id:{$identifier}";
    }

    public function ipFailuresKey(string $guard, string $ip): string
    {
        return "password-login:{$guard}:failures:ip:{$ip}";
    }

    /**
     * The two throttled dimensions checked by the middleware. The identifier
     * dimension comes first so a full identifier window (the more targeted
     * signal) wins the 429.
     *
     * @return list<array{window: string, failures: string}>
     */
    public function dimensions(string $guard, string $identifier, string $ip): array
    {
        return [
            [
                'window'   => $this->idWindowKey($guard, $identifier),
                'failures' => $this->idFailuresKey($guard, $identifier),
            ],
            [
                'window'   => $this->ipWindowKey($guard, $ip),
                'failures' => $this->ipFailuresKey($guard, $ip),
            ],
        ];
    }

    public function failures(string $key): int
    {
        return (int) RateLimiter::attempts($key);
    }

    /**
     * Lockout window (seconds) for the given consecutive failure count.
     * Below the first tier the baseline 1-minute window applies.
     */
    public function lockoutSeconds(string $guard, int $failures): int
    {
        return (int) ($this->tierFor($guard, $failures)['decay_minutes'] ?? 1) * 60;
    }

    /**
     * Count a login attempt (successful or not) against both dimensions.
     * The lockout decay is computed from the failure count AFTER this attempt,
     * so the very next 429 already carries the escalated Retry-After.
     */
    public function recordAttempt(string $guard, string $identifier, string $ip): void
    {
        foreach ($this->dimensions($guard, $identifier, $ip) as $dimension) {
            $this->recordForKey($guard, $dimension['failures'], $dimension['window']);
        }
    }

    /**
     * Reset every counter for the identifier and IP pair (called on success).
     */
    public function clear(string $guard, string $identifier, string $ip): void
    {
        foreach ($this->dimensions($guard, $identifier, $ip) as $dimension) {
            RateLimiter::clear($dimension['window']);
            RateLimiter::clear($dimension['failures']);
        }
    }

    private function recordForKey(string $guard, string $failuresKey, string $windowKey): void
    {
        $ttlSeconds = (int) config("password_throttle.{$guard}.failure_counter_ttl_seconds", 3600);

        $failures = RateLimiter::hit($failuresKey, $ttlSeconds);

        $lockoutSeconds = $this->lockoutSeconds($guard, $failures);

        RateLimiter::hit($windowKey, $lockoutSeconds);
    }

    /**
     * Highest tier whose failure threshold has been reached, or null.
     *
     * @return array{failures: int, decay_minutes: int}|null
     */
    private function tierFor(string $guard, int $failures): ?array
    {
        $matched = null;

        foreach ((array) ($this->configFor($guard)['tiers'] ?? []) as $tier) {
            if ($failures >= (int) $tier['failures']) {
                $matched = $tier;
            }
        }

        return $matched;
    }

    /**
     * @return array<string, mixed>
     */
    private function configFor(string $guard): array
    {
        $config = config("password_throttle.{$guard}");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown password login throttle guard [{$guard}].");
        }

        return $config;
    }
}
