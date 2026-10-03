<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Services\PgroongaService;
use Illuminate\Support\Facades\DB;

covers(PgroongaService::class);

it('serves the PGroonga probe through the cache gateway', function (): void {
    app(CacheStore::class)->put(CacheKey::PgroongaEnabled, [], true);

    expect(app(PgroongaService::class)->isPgroongaEnabled())->toBeTrue();
});

it('caches the probe result for later reads', function (): void {
    $cache = app(CacheStore::class);
    $cache->forget(CacheKey::PgroongaEnabled);

    DB::enableQueryLog();

    $result = app(PgroongaService::class)->isPgroongaEnabled();

    expect($result)->toBeBool()
        ->and(collect(DB::getQueryLog())->contains(
            fn (array $query): bool => str_contains($query['query'], 'pg_extension')
        ))->toBeTrue()
        ->and($cache->get(CacheKey::PgroongaEnabled))->toBe($result);
});

it('assumes PGroonga is unavailable when the database probe fails', function (): void {
    $cache = app(CacheStore::class);
    $cache->forget(CacheKey::PgroongaEnabled);

    // Force the failure with a real database that has no pg_extension catalog
    // rather than mocking the connection, so the catch branch is exercised by
    // an actual QueryException. Touch the default connection first so the
    // test transaction stays on PostgreSQL.
    DB::select('SELECT 1');

    $original = DB::getDefaultConnection();
    config()->set('database.connections.pgroonga_probe_failure', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);
    DB::setDefaultConnection('pgroonga_probe_failure');

    try {
        expect(app(PgroongaService::class)->isPgroongaEnabled())->toBeFalse();
    } finally {
        DB::setDefaultConnection($original);
    }
});
