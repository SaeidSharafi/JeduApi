<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Enums\System\CacheTag;
use App\Services\Cache\LaravelCacheStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;

covers(LaravelCacheStore::class);

/**
 * The gateway contract runs on the array store, the driver the suite uses.
 */
function cacheGateway(): CacheStore
{
    config(['cache.default' => 'array']);
    Cache::store('array')->flush();

    return app(CacheStore::class);
}

it('binds the gateway contract to the laravel implementation', function (): void {
    expect(cacheGateway())->toBeInstanceOf(LaravelCacheStore::class);
});

it('reads back a value written for the same key parameters', function (): void {
    $cache = cacheGateway();

    $cache->put(CacheKey::AccessToken, ['hash' => 'abc'], 'token');

    expect($cache->get(CacheKey::AccessToken, ['hash' => 'abc']))->toBe('token');
});

it('keeps different key parameters apart', function (): void {
    $cache = cacheGateway();

    $cache->put(CacheKey::AccessToken, ['hash' => 'abc'], 'first');
    $cache->put(CacheKey::AccessToken, ['hash' => 'def'], 'second');

    expect($cache->get(CacheKey::AccessToken, ['hash' => 'abc']))->toBe('first')
        ->and($cache->get(CacheKey::AccessToken, ['hash' => 'def']))->toBe('second');
});

it('returns the default when nothing is stored for the key', function (): void {
    $cache = cacheGateway();

    expect($cache->get(CacheKey::Settings, [], 'fallback'))->toBe('fallback');
});

it('generates a remembered value once and serves it from the cache afterwards', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): string {
        $calls++;

        return 'generated';
    };

    $first  = $cache->remember(CacheKey::UserProfile, ['id' => 7], $callback);
    $second = $cache->remember(CacheKey::UserProfile, ['id' => 7], $callback);

    expect($first)->toBe('generated')
        ->and($second)->toBe('generated')
        ->and($calls)->toBe(1);
});

it('regenerates a remembered value after its registered ttl', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): int {
        return ++$calls;
    };

    expect($cache->remember(CacheKey::AccessToken, ['hash' => 'abc'], $callback))->toBe(1);

    $this->travel(361)->seconds();

    expect($cache->remember(CacheKey::AccessToken, ['hash' => 'abc'], $callback))->toBe(2);
});

it('does not cache a null value', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): ?string {
        $calls++;

        return $calls === 1 ? null : 'generated';
    };

    expect($cache->remember(CacheKey::UserProfile, ['id' => 7], $callback))->toBeNull()
        ->and($cache->remember(CacheKey::UserProfile, ['id' => 7], $callback))->toBe('generated')
        ->and($calls)->toBe(2);
});

it('ignores a null value written directly', function (): void {
    // Storing null is indistinguishable from a miss through the gateway's own
    // reads, so the write is inspected instead of the resulting state.
    $repository = Mockery::mock(Repository::class);
    $repository->shouldNotReceive('put');

    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('store')->andReturn($repository);

    (new LaravelCacheStore($factory))->put(CacheKey::Settings, [], null);
});

it('keeps a remembered-forever value far beyond any registered ttl', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): int {
        return ++$calls;
    };

    expect($cache->rememberForever(CacheKey::Settings, [], $callback))->toBe(1);

    $this->travel(2)->years();

    expect($cache->rememberForever(CacheKey::Settings, [], $callback))->toBe(1)
        ->and($calls)->toBe(1);
});

it('forgets only the exact key it is given', function (): void {
    $cache = cacheGateway();

    $cache->put(CacheKey::AccessToken, ['hash' => 'abc'], 'first');
    $cache->put(CacheKey::AccessToken, ['hash' => 'def'], 'second');

    $cache->forget(CacheKey::AccessToken, ['hash' => 'abc']);

    expect($cache->get(CacheKey::AccessToken, ['hash' => 'abc']))->toBeNull()
        ->and($cache->get(CacheKey::AccessToken, ['hash' => 'def']))->toBe('second');
});

it('makes the previous generation unreadable and regenerates it on the next read', function (): void {
    $cache = cacheGateway();

    $cache->put(CacheKey::Slider, [], 'first');
    expect($cache->get(CacheKey::Slider))->toBe('first');

    $cache->invalidate(CacheTag::HomePage);
    expect($cache->get(CacheKey::Slider))->toBeNull();

    expect($cache->remember(CacheKey::Slider, [], fn (): string => 'second'))->toBe('second')
        ->and($cache->get(CacheKey::Slider))->toBe('second');
});

it('invalidates every tag it is given without touching the others', function (): void {
    $cache = cacheGateway();

    $cache->put(CacheKey::Slider, [], 'sliders');
    $cache->put(CacheKey::Settings, [], 'settings');
    $cache->put(CacheKey::DiscountHandlers, [], 'handlers');

    $cache->invalidate(CacheTag::HomePage, CacheTag::Settings);

    expect($cache->get(CacheKey::Slider))->toBeNull()
        ->and($cache->get(CacheKey::Settings))->toBeNull()
        ->and($cache->get(CacheKey::DiscountHandlers))->toBe('handlers');
});

it('keeps the previous generation unreachable while its values are still stored', function (): void {
    $cache = cacheGateway();

    $cache->put(CacheKey::UserProfile, ['id' => 7], 'first');
    $cache->invalidate(CacheTag::Auth);

    $this->travel(1)->hour();

    expect($cache->get(CacheKey::UserProfile, ['id' => 7]))->toBeNull();
});

it('serves a stale value and refreshes it after the response', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): string {
        return 'v'.++$calls;
    };

    expect($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v1');

    $this->travel(301)->seconds();

    expect($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v1');

    app(DeferredCallbackCollection::class)->invoke();

    expect($calls)->toBe(2)
        ->and($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v2');
});

it('serves a value as stale until its stale window ends', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): string {
        return 'v'.++$calls;
    };

    expect($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v1');

    // Slider is fresh for 300 seconds and stale for another 900, so at 1000
    // seconds it is still stored and must be served stale rather than regenerated.
    $this->travel(1000)->seconds();

    expect($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v1');

    // Past the total lifetime it is regenerated.
    $this->travel(202)->seconds();

    expect($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v2');
});

it('passes an explicit single-flight lock into the flexible refresh', function (): void {
    // Whether the framework holds the refresh lock for a bounded time is not
    // observable from cache state on the array store, so the call is inspected
    // here: an unset argument silently collapses to a one-second lock on Redis.
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('get')->andReturn(0);
    $repository->shouldReceive('flexible')
        ->once()
        ->withArgs(fn (string $key, array $ttl, Closure $callback, array $lock): bool => ($lock['seconds'] ?? 0) > 0)
        ->andReturn('value');

    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('store')->andReturn($repository);

    $cache = new LaravelCacheStore($factory);

    expect($cache->flexible(CacheKey::Slider, [], fn (): string => 'value'))->toBe('value');
});

it('refuses to read flexibly when the registry declares no stale window', function (): void {
    $cache = cacheGateway();

    expect(fn (): mixed => $cache->flexible(CacheKey::GoodForStart, ['slug' => 'math', 'limit' => 10], fn (): string => 'value'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to read a forever key flexibly', function (): void {
    $cache = cacheGateway();

    expect(fn (): mixed => $cache->flexible(CacheKey::Settings, [], fn (): string => 'value'))
        ->toThrow(InvalidArgumentException::class);
});

it('bumps the version counters on the database store across generations', function (): void {
    Cache::store('database')->flush();

    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('store')->andReturn(Cache::store('database'));

    $cache = new LaravelCacheStore($factory);

    $cache->put(CacheKey::UserProfile, ['id' => 7], 'first');
    $cache->invalidate(CacheTag::Auth);

    $this->travel(1)->hour();

    expect($cache->get(CacheKey::UserProfile, ['id' => 7]))->toBeNull();

    $cache->put(CacheKey::UserProfile, ['id' => 7], 'second');
    $cache->invalidate(CacheTag::Auth);

    expect($cache->get(CacheKey::UserProfile, ['id' => 7]))->toBeNull();
});
