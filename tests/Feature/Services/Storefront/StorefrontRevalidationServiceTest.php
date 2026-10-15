<?php

declare(strict_types=1);

use App\Services\Storefront\StorefrontRevalidationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

mutates(StorefrontRevalidationService::class);

beforeEach(function (): void {
    config()->set('storefront.url', 'https://shop.test');
    config()->set('storefront.revalidate_secret', 'shared-secret');
    config()->set('storefront.timeout', 3);
});

it('posts the tags with the shared secret', function (): void {
    Http::fake(['shop.test/*' => Http::response(['ok' => true], 200)]);

    app(StorefrontRevalidationService::class)->revalidate(['catalog:courses', 'home:blocks']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://shop.test/api/revalidate'
        && $request->method()                                       === 'POST'
        && $request->hasHeader('x-revalidate-secret', 'shared-secret')
        && $request->data() === ['tags' => ['catalog:courses', 'home:blocks']]);
});

it('does not send a request when the environment is not configured', function (string $key): void {
    config()->set($key, null);

    app(StorefrontRevalidationService::class)->revalidate(['catalog:courses']);

    Http::assertNothingSent();
})->with([
    'no secret' => 'storefront.revalidate_secret',
    'no url'    => 'storefront.url',
]);

it('does not send a request for an empty tag list', function (): void {
    app(StorefrontRevalidationService::class)->revalidate([]);

    Http::assertNothingSent();
});

it('treats a permanent rejection as an answered call, not an error', function (int $status): void {
    Http::fake(['shop.test/*' => Http::response(['error' => 'nope', 'rejected' => ['catalog:nope']], $status)]);

    app(StorefrontRevalidationService::class)->revalidate(['catalog:nope']);

    Http::assertSentCount(1);
})->with([400, 401, 404, 405, 503]);

it('throws on a retryable server failure so the queue retries', function (int $status): void {
    Http::fake(['shop.test/*' => Http::response(['error' => 'boom'], $status)]);

    expect(fn () => app(StorefrontRevalidationService::class)->revalidate(['catalog:courses']))
        ->toThrow(RuntimeException::class);
})->with([500, 502, 504]);

it('lets a connection failure surface so the queue retries', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('storefront is unreachable'));

    expect(fn () => app(StorefrontRevalidationService::class)->revalidate(['catalog:courses']))
        ->toThrow(ConnectionException::class);
});
