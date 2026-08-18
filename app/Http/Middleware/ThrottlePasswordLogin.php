<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\PasswordLoginThrottleService;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Progressive-backoff throttle for password login endpoints.
 *
 * Usage: ->middleware('throttle.password-login:shop') or ':staff'.
 *
 * Enforces the per-identifier and per-IP window baseline (429 once exceeded)
 * and delegates counter bookkeeping to PasswordLoginThrottleService: every
 * attempt is recorded up front and a successful login clears all counters.
 */
final class ThrottlePasswordLogin
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        $throttle    = app(PasswordLoginThrottleService::class);
        $identifier  = mb_strtolower(mb_trim((string) $request->input('identifier')));
        $ip          = (string) $request->ip();
        $maxAttempts = $throttle->maxAttempts($guard);

        foreach ($throttle->windowKeys($guard, $identifier, $ip) as $key) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($key);

                throw new ThrottleRequestsException(
                    __('messages.auth.login.throttled', ['seconds' => $retryAfter]),
                    null,
                    [
                        'Retry-After'           => (string) $retryAfter,
                        'X-RateLimit-Limit'     => (string) $maxAttempts,
                        'X-RateLimit-Remaining' => '0',
                    ]
                );
            }
        }

        $throttle->recordAttempt($guard, $identifier, $ip);

        $response = $next($request);

        if ($response->isSuccessful()) {
            $throttle->clear($guard, $identifier, $ip);
        }

        return $response;
    }
}
