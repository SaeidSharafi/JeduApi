<?php

declare(strict_types=1);

use App\Console\Commands\Cache\ShowCacheVersionsCommand;
use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;

covers(ShowCacheVersionsCommand::class);

it('prints the current version of every group', function (): void {
    $this->artisan('cache:versions')
        ->expectsTable(
            ['Group', 'Version'],
            [
                ['home_page', '0'],
                ['content', '0'],
                ['catalog', '0'],
                ['search', '0'],
                ['discounts', '0'],
                ['settings', '0'],
                ['auth', '0'],
            ],
        )
        ->assertExitCode(0);
});

it('shows the bumped version only for the invalidated groups', function (): void {
    $cache = app(CacheStore::class);

    $cache->invalidate(CacheTag::Search, CacheTag::Auth);
    $cache->invalidate(CacheTag::Search);

    $this->artisan('cache:versions')
        ->expectsTable(
            ['Group', 'Version'],
            [
                ['home_page', '0'],
                ['content', '0'],
                ['catalog', '0'],
                ['search', '2'],
                ['discounts', '0'],
                ['settings', '0'],
                ['auth', '1'],
            ],
        )
        ->assertExitCode(0);
});
