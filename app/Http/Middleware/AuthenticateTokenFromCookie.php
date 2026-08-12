<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AuthenticateTokenFromCookie
{
    public function handle(Request $request, Closure $next, string $guard = 'user'): Response
    {
        if (! in_array($guard, ['user', 'staff'], true)) {
            throw new AuthenticationException();
        }

        $cookieName = $guard.'_token';

        if (! $request->hasHeader('Authorization') && $request->hasCookie($cookieName)) {
            $this->ensureRequestOriginIsAllowed($request);

            $token = $request->cookie($cookieName);
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $next($request);
    }

    private function ensureRequestOriginIsAllowed(Request $request): void
    {
        if ($request->isMethodSafe()) {
            return;
        }

        $origin = $request->header('Origin');

        if ($origin === null && $request->headers->has('Referer')) {
            $referer = parse_url((string) $request->header('Referer'));
            $origin  = isset($referer['scheme'], $referer['host'])
                ? $referer['scheme'].'://'.$referer['host'].(isset($referer['port']) ? ':'.$referer['port'] : '')
                : null;
        }

        $allowedOrigins   = Arr::wrap(config('cors.allowed_origins'));
        $allowedOrigins[] = config('app.url');
        $allowedOrigins   = array_map(
            static fn (mixed $allowedOrigin): string => mb_rtrim((string) $allowedOrigin, '/'),
            $allowedOrigins
        );

        if ($origin === null || ! in_array(mb_rtrim($origin, '/'), $allowedOrigins, true)) {
            throw new AccessDeniedHttpException(__('messages.forbidden'));
        }
    }
}
