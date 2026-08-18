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
 * Checks each throttled dimension (identifier, IP): the allowed attempts per
 * window come from the consecutive-failure count, so once a lockout tier is
 * reached a single failed attempt is followed by the tier's lockout window
 * (5 -> 1 min, 10 -> 15 min, 15 -> 1 hour). Counter bookkeeping is delegated
 * to PasswordLoginThrottleService: every attempt is recorded up front and a
 * successful login clears all counters.
 */
final class ThrottlePasswordLogin
{
    public function handle(Request $request, Closure $next, string $guard): Response
    {
        $throttle   = app(PasswordLoginThrottleService::class);
        $identifier = mb_strtolower(mb_trim((string) $request->input('identifier')));
        $ip         = (string) $request->ip();

        foreach ($throttle->dimensions($guard, $identifier, $ip) as $dimension) {
            $maxAttempts = $throttle->maxAttempts($guard, $throttle->failures($dimension['failures']));

            if (RateLimiter::tooManyAttempts($dimension['window'], $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($dimension['window']);

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
