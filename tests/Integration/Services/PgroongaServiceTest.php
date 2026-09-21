<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Services\PgroongaService;
use Illuminate\Database\QueryException;
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

    DB::shouldReceive('selectOne')
        ->once()
        ->andThrow(new QueryException(
            'pgsql',
            "SELECT 1 FROM pg_extension WHERE extname = 'pgroonga'",
            [],
            new RuntimeException('database unavailable'),
        ));

    expect(app(PgroongaService::class)->isPgroongaEnabled())->toBeFalse();
});
