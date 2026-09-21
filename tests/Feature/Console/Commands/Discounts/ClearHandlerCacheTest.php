<?php

declare(strict_types=1);

use App\Console\Commands\Discounts\ClearHandlerCache;
use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Services\Discounts\DiscountHandlerRegistry;

covers(ClearHandlerCache::class);

it('clears the cached handler registry through the gateway', function (): void {
    $cache = app(CacheStore::class);
    $cache->put(CacheKey::DiscountHandlers, [], ['cartConditions' => ['stale' => 'StaleHandler']]);

    $this->artisan('discounts:clear-cache')
        ->expectsOutput('Clearing discount handler cache...')
        ->expectsOutput('Discount handler cache cleared successfully.')
        ->assertExitCode(0);

    expect($cache->get(CacheKey::DiscountHandlers))->toBeNull();
});

it('re-discovers handlers on the next use after clearing', function (): void {
    config()->set('app.debug', false);

    $cache = app(CacheStore::class);
    $cache->put(CacheKey::DiscountHandlers, [], ['cartConditions' => ['stale' => 'StaleHandler']]);

    // The stale generation is served until the registry cache is cleared.
    expect(app(DiscountHandlerRegistry::class)->getCartConditionHandler('stale'))->toBe('StaleHandler');

    $this->artisan('discounts:clear-cache')->assertExitCode(0);

    $this->app->forgetInstance(DiscountHandlerRegistry::class);

    $registry = app(DiscountHandlerRegistry::class);

    expect($registry->getCartConditionHandler('stale'))->toBeNull()
        ->and($cache->get(CacheKey::DiscountHandlers))->not->toBeNull();
});
