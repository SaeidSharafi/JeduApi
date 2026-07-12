<?php

declare(strict_types=1);

use App\Exceptions\Gateway\DigipayException;
use App\Services\Payment\Digipay\DigipayAuthenticator;
use App\Services\Payment\Digipay\DigipayConfigRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('payments.digipay.endpoints.sandbox.base_url', 'https://api.digipay.test');
    config()->set('payments.digipay.paths.oauth_token', '/digipay/api/oauth/token');
    config()->set('payments.digipay.token_cache.buffer', 300);
    config()->set('payments.digipay.timeout', 30);

    $this->mock(DigipayConfigRepository::class, function ($mock): void {
        $mock->shouldReceive('getClientId')->andReturn('test-client-id');
        $mock->shouldReceive('getClientSecret')->andReturn('test-client-secret');
        $mock->shouldReceive('getUsername')->andReturn('test-user');
        $mock->shouldReceive('getPassword')->andReturn('test-pass');
        $mock->shouldReceive('getBaseUrl')->andReturn('https://api.digipay.test');
        $mock->shouldReceive('getTimeout')->andReturn(30);
    });

    Cache::forget('digipay_access_token');
});

// ─── Cache miss — fetches from OAuth ──────────────────────────────────

it('fetches and caches a new token when cache is empty', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/oauth/token' => Http::response([
            'access_token' => 'fresh-test-token',
            'token_type'   => 'bearer',
            'expires_in'   => 3600,
        ], 200),
    ]);

    $authenticator = app(DigipayAuthenticator::class);

    $token = $authenticator->getAccessToken();

    expect($token)->toBe('fresh-test-token');

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        return $request->url() === 'https://api.digipay.test/digipay/api/oauth/token'
            && $request->isForm()
            && $request['username'] === 'test-user'
            && $request['password'] === 'test-pass'
            && $request['grant_type'] === 'password';
    });

    expect(Cache::get('digipay_access_token'))->toBe('fresh-test-token');
});

// ─── Cache hit — returns cached token ──────────────────────────────────

it('returns cached token without HTTP call when token exists in cache', function (): void {
    Cache::put('digipay_access_token', 'cached-token-value', 600);

    Http::fake();

    $authenticator = app(DigipayAuthenticator::class);

    $token = $authenticator->getAccessToken();

    expect($token)->toBe('cached-token-value');

    Http::assertNothingSent();
});

// ─── Auth failure — throws DigipayException ────────────────────────────

it('throws DigipayException when OAuth endpoint returns 401', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/oauth/token' => Http::response(null, 401),
    ]);

    $authenticator = app(DigipayAuthenticator::class);

    expect(fn () => $authenticator->getAccessToken())
        ->toThrow(DigipayException::class, 'Digipay authentication failed');
});

it('throws DigipayException when OAuth response lacks access_token', function (): void {
    Http::fake([
        'api.digipay.test/digipay/api/oauth/token' => Http::response([
            'error' => 'invalid_grant',
        ], 200),
    ]);

    $authenticator = app(DigipayAuthenticator::class);

    expect(fn () => $authenticator->getAccessToken())
        ->toThrow(DigipayException::class, 'Digipay authentication failed');
});

// ─── Token TTL respects buffer ─────────────────────────────────────────

it('caches token with TTL reduced by configured buffer', function (): void {
    config()->set('payments.digipay.token_cache.buffer', 600);

    Http::fake([
        'api.digipay.test/digipay/api/oauth/token' => Http::response([
            'access_token' => 'ttl-test-token',
            'expires_in'   => 3600,
        ], 200),
    ]);

    $authenticator = app(DigipayAuthenticator::class);

    $authenticator->getAccessToken();

    expect(Cache::get('digipay_access_token'))->toBe('ttl-test-token');
});

// ─── clearToken() ──────────────────────────────────────────────────────

it('clears cached token when clearToken is called', function (): void {
    Cache::put('digipay_access_token', 'stale-token', 600);

    $authenticator = app(DigipayAuthenticator::class);

    $authenticator->clearToken();

    expect(Cache::has('digipay_access_token'))->toBeFalse();
});
