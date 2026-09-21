<?php

declare(strict_types=1);

use App\Console\Commands\Cache\InvalidateCacheGroupCommand;
use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Enums\System\CacheTag;

covers(InvalidateCacheGroupCommand::class);

it('invalidates exactly the requested group and leaves the others untouched', function (): void {
    $cache = app(CacheStore::class);

    $cache->put(CacheKey::Slider, [], 'sliders');
    $cache->put(CacheKey::Settings, [], 'settings');

    $this->artisan('cache:invalidate', ['group' => 'home_page'])
        ->expectsOutput('Cache group [home_page] invalidated; its version is now 1.')
        ->assertExitCode(0);

    expect($cache->get(CacheKey::Slider))->toBeNull()
        ->and($cache->get(CacheKey::Settings))->toBe('settings')
        ->and($cache->version(CacheTag::HomePage))->toBe(1)
        ->and($cache->version(CacheTag::Settings))->toBe(0);
});

it('bumps the group again on a second invocation', function (): void {
    $this->artisan('cache:invalidate home_page')->assertExitCode(0);
    $this->artisan('cache:invalidate home_page')
        ->expectsOutput('Cache group [home_page] invalidated; its version is now 2.')
        ->assertExitCode(0);

    expect(app(CacheStore::class)->version(CacheTag::HomePage))->toBe(2);
});

it('rejects an unknown group with an explicit error and changes nothing', function (): void {
    $cache = app(CacheStore::class);
    $cache->put(CacheKey::Slider, [], 'sliders');

    $this->artisan('cache:invalidate bogus')
        ->expectsOutput('Unknown cache group [bogus]. Available groups: home_page, content, catalog, search, discounts, settings, auth.')
        ->assertExitCode(1);

    expect($cache->get(CacheKey::Slider))->toBe('sliders')
        ->and($cache->version(CacheTag::HomePage))->toBe(0);
});
