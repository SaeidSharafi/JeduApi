<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Enums\System\CacheTag;
use App\Services\Cache\LaravelCacheStore;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

it('returns null when nothing is stored for the key', function (): void {
    $cache = cacheGateway();

    expect($cache->get(CacheKey::Settings))->toBeNull();
});

it('generates a remembered value once and serves it from the cache afterwards', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): string {
        $calls++;

        return 'generated';
    };

    $first  = $cache->remember(CacheKey::AccessToken, ['hash' => 'profile'], $callback);
    $second = $cache->remember(CacheKey::AccessToken, ['hash' => 'profile'], $callback);

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

    expect($cache->remember(CacheKey::AccessToken, ['hash' => 'profile'], $callback))->toBeNull()
        ->and($cache->remember(CacheKey::AccessToken, ['hash' => 'profile'], $callback))->toBe('generated')
        ->and($calls)->toBe(2);
});

it('ignores a null value written directly', function (): void {
    // Storing null is indistinguishable from a miss through the gateway's own
    // reads, so the real store is inspected: the write must add no row at all.
    config(['cache.default' => 'database']);
    Cache::store('database')->flush();

    $table  = config('cache.stores.database.table');
    $before = DB::table($table)->count();

    app(CacheStore::class)->put(CacheKey::Settings, [], null);

    expect(DB::table($table)->count())->toBe($before);
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

it('reports each tag version and increases only the invalidated tag', function (): void {
    $cache = cacheGateway();

    expect($cache->version(CacheTag::Search))->toBe(0);

    $cache->invalidate(CacheTag::Search);
    $cache->invalidate(CacheTag::Search);

    expect($cache->version(CacheTag::Search))->toBe(2)
        ->and($cache->version(CacheTag::HomePage))->toBe(0);
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

    $cache->put(CacheKey::Settings, [], 'first');
    $cache->invalidate(CacheTag::Settings);

    $this->travel(1)->hour();

    expect($cache->get(CacheKey::Settings))->toBeNull();
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

it('acquires the framework single-flight lock before refreshing flexibly', function (): void {
    $cache    = cacheGateway();
    $calls    = 0;
    $callback = function () use (&$calls): string {
        return 'v'.++$calls;
    };

    expect($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v1');

    $this->travel(301)->seconds();

    // Hold the lock the framework names for this key, the way a concurrent
    // refresh would, and prove the deferred refresh cannot regenerate through it.
    $resolved = sprintf(
        'cache:%s:v%d:%s',
        CacheTag::HomePage->value,
        $cache->version(CacheTag::HomePage),
        CacheKey::Slider->resolve(),
    );
    $lock = Cache::store('array')->lock('illuminate:cache:flexible:lock:'.$resolved, 30);

    expect($lock->get())->toBeTrue();

    expect($cache->flexible(CacheKey::Slider, [], $callback))->toBe('v1');

    app(DeferredCallbackCollection::class)->invoke();

    expect($calls)->toBe(1);

    $lock->release();
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
    config(['cache.default' => 'database']);
    Cache::store('database')->flush();

    $cache = app(CacheStore::class);

    $cache->put(CacheKey::Settings, [], 'first');
    $cache->invalidate(CacheTag::Settings);

    $this->travel(1)->hour();

    expect($cache->get(CacheKey::Settings))->toBeNull()
        ->and($cache->version(CacheTag::Settings))->toBe(1);

    $cache->put(CacheKey::Settings, [], 'second');
    $cache->invalidate(CacheTag::Settings);

    expect($cache->get(CacheKey::Settings))->toBeNull()
        ->and($cache->version(CacheTag::Settings))->toBe(2);
});
