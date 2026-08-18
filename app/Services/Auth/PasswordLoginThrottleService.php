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
 * Each dimension keeps a window counter (the "5/min baseline" enforced by the
 * route middleware) and a consecutive-failure counter that drives the lockout
 * escalation (5 -> 1 min, 10 -> 15 min, 15 -> 1 hour). The failure counter is
 * only reset by a successful login, so spaced-out attacks still escalate.
 */
final class PasswordLoginThrottleService
{
    public function maxAttempts(string $guard): int
    {
        $config = config("password_throttle.{$guard}");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown password login throttle guard [{$guard}].");
        }

        return (int) ($config['max_attempts'] ?? 5);
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
     * Window keys checked by the middleware, identifier first so a full
     * identifier window (the more targeted signal) wins the 429.
     *
     * @return array{0: string, 1: string}
     */
    public function windowKeys(string $guard, string $identifier, string $ip): array
    {
        return [
            $this->idWindowKey($guard, $identifier),
            $this->ipWindowKey($guard, $ip),
        ];
    }

    public function failures(string $key): int
    {
        return RateLimiter::attempts($key);
    }

    /**
     * Lockout window (seconds) for the given consecutive failure count.
     * Below the first tier the baseline 1-minute window applies.
     */
    public function lockoutSeconds(string $guard, int $failures): int
    {
        $seconds = 60;
        $config  = config("password_throttle.{$guard}");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown password login throttle guard [{$guard}].");
        }

        foreach ((array) ($config['tiers'] ?? []) as $tier) {
            if ($failures >= (int) $tier['failures']) {
                $seconds = (int) $tier['decay_minutes'] * 60;
            }
        }

        return $seconds;
    }

    /**
     * Count a login attempt (successful or not) against both dimensions.
     * The lockout decay is computed from the failure count AFTER this attempt,
     * so the very next 429 already carries the escalated Retry-After.
     */
    public function recordAttempt(string $guard, string $identifier, string $ip): void
    {
        $this->recordForKey(
            $guard,
            $this->idFailuresKey($guard, $identifier),
            $this->idWindowKey($guard, $identifier)
        );
        $this->recordForKey(
            $guard,
            $this->ipFailuresKey($guard, $ip),
            $this->ipWindowKey($guard, $ip)
        );
    }

    /**
     * Reset every counter for the identifier and IP pair (called on success).
     */
    public function clear(string $guard, string $identifier, string $ip): void
    {
        foreach ([
            $this->idWindowKey($guard, $identifier),
            $this->ipWindowKey($guard, $ip),
            $this->idFailuresKey($guard, $identifier),
            $this->ipFailuresKey($guard, $ip),
        ] as $key) {
            RateLimiter::clear($key);
        }
    }

    private function recordForKey(string $guard, string $failuresKey, string $windowKey): void
    {
        $ttlSeconds = (int) config("password_throttle.{$guard}.failure_counter_ttl_seconds", 3600);

        RateLimiter::hit($failuresKey, $ttlSeconds);

        $lockoutSeconds = $this->lockoutSeconds($guard, RateLimiter::attempts($failuresKey));

        RateLimiter::hit($windowKey, $lockoutSeconds);
    }
}
