<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AuthenticateTokenFromCookie;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

describe('AuthenticateTokenFromCookie', function (): void {
    beforeEach(function (): void {
        $this->middleware    = new AuthenticateTokenFromCookie();
        $this->passedRequest = null;

        $this->next = function (Request $request): Response {
            $this->passedRequest = $request;

            return new Response('ok', 200);
        };
    });

    test('it throws AuthenticationException for an invalid guard', function (): void {
        $request = Request::create('/api/v1/admin/users', 'GET');

        expect(fn () => $this->middleware->handle($request, $this->next, 'admin'))
            ->toThrow(AuthenticationException::class);
    });

    test('it copies the user token cookie into the Authorization header for safe methods', function (): void {
        $request = Request::create('/api/v1/admin/users', 'GET');
        $request->cookies->set('user_token', 'abc123');

        $this->middleware->handle($request, $this->next);

        expect($this->passedRequest->header('Authorization'))->toBe('Bearer abc123');
    });

    test('it copies the staff token cookie into the Authorization header', function (): void {
        $request = Request::create('/api/v1/admin/users', 'GET');
        $request->cookies->set('staff_token', 'xyz789');

        $this->middleware->handle($request, $this->next, 'staff');

        expect($this->passedRequest->header('Authorization'))->toBe('Bearer xyz789');
    });

    test('it does not overwrite an existing Authorization header when a cookie is present', function (): void {
        $request = Request::create('/api/v1/admin/users', 'GET');
        $request->headers->set('Authorization', 'Bearer original');
        $request->cookies->set('user_token', 'abc123');

        $this->middleware->handle($request, $this->next);

        expect($this->passedRequest->header('Authorization'))->toBe('Bearer original');
    });

    test('it passes the request through unchanged when neither cookie nor Authorization header exists', function (): void {
        $request = Request::create('/api/v1/admin/users', 'GET');

        $this->middleware->handle($request, $this->next);

        expect($this->passedRequest->hasHeader('Authorization'))->toBeFalse();
    });

    test('it rejects a non-safe method with a cookie when the Origin is not allowed', function (): void {
        config()->set('cors.allowed_origins', ['https://allowed.example']);
        config()->set('app.url', 'http://localhost');

        $request = Request::create('/api/v1/admin/users', 'POST');
        $request->cookies->set('user_token', 'abc123');
        $request->headers->set('Origin', 'https://evil.example');

        expect(fn () => $this->middleware->handle($request, $this->next))
            ->toThrow(AccessDeniedHttpException::class);
    });

    test('it allows a non-safe method with a cookie when the Origin is in the allowed origins', function (): void {
        config()->set('cors.allowed_origins', ['https://allowed.example']);
        config()->set('app.url', 'http://localhost');

        $request = Request::create('/api/v1/admin/users', 'POST');
        $request->cookies->set('user_token', 'abc123');
        $request->headers->set('Origin', 'https://allowed.example');

        $this->middleware->handle($request, $this->next);

        expect($this->passedRequest->header('Authorization'))->toBe('Bearer abc123');
    });

    test('it derives the Origin from the Referer header when Origin is missing', function (): void {
        config()->set('cors.allowed_origins', []);
        config()->set('app.url', 'http://localhost/'); // Trailing slash must be trimmed before comparing

        $request = Request::create('/api/v1/admin/users', 'POST');
        $request->cookies->set('user_token', 'abc123');
        $request->headers->set('Referer', 'http://localhost/foo');

        $this->middleware->handle($request, $this->next);

        expect($this->passedRequest->header('Authorization'))->toBe('Bearer abc123');
    });

    test('it rejects a non-safe method with a cookie when neither Origin nor Referer is present', function (): void {
        config()->set('cors.allowed_origins', ['https://allowed.example']);
        config()->set('app.url', 'http://localhost');

        $request = Request::create('/api/v1/admin/users', 'POST');
        $request->cookies->set('user_token', 'abc123');

        expect(fn () => $this->middleware->handle($request, $this->next))
            ->toThrow(AccessDeniedHttpException::class);
    });
});
