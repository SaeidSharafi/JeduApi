<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\System\SearchEngine;
use App\Enums\System\SettingKeyEnum;
use App\Models\Product;
use App\Services\SearchEngineService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;

mutates(SearchEngineService::class);

/**
 * The engine is asserted through the one seam that already serves both search callers:
 * the Product search scope. Returned rows are the only evidence used — not the SQL, the
 * query log, or the cache key.
 *
 * `PGROONGA_PARTIAL_TOKEN` is the term that matters. It matches through PGroonga's `&@~`
 * but not through the native `to_tsvector(...) @@ plainto_tsquery(...)` fallback, so a
 * test using it fails if the query path quietly stops gating on the engine. Asserting a
 * full word would pass under either engine and would guard nothing.
 *
 * What is deliberately not asserted here: that PGroonga actually matches the partial
 * token. That needs a real PGroonga index, and index DDL on the shared tables deadlocks
 * against the schema the shared TestCase tears down when the suite runs with `--parallel`
 * — which is how CI runs. It is verified against a real environment instead.
 */
const PGROONGA_PARTIAL_TOKEN = 'Widg';

function chooseSearchEngine(SearchEngine $engine): void
{
    app(SettingsService::class)->set(SettingKeyEnum::SEARCH_ENGINE, $engine->value, 'string', 'search');
}

function publishedProductNamed(string $name): Product
{
    return Product::factory()->create([
        'name'       => $name,
        'status'     => PublicationStatusEnum::PUBLISHED->value,
        'is_visible' => true,
    ]);
}

it('does not match the pgroonga-only partial token while the typesense engine is in use', function (): void {
    chooseSearchEngine(SearchEngine::TYPESENSE);
    $product = publishedProductNamed('Advanced Widgetry');

    $found = Product::query()->search(PGROONGA_PARTIAL_TOKEN)->get();

    // The non-PGroonga engine answers through the database's own full-text search, which
    // matches whole lexemes only. This is the assertion that proves the path is gated.
    expect($found->pluck('id')->all())->not->toContain($product->id);
});

it('still finds a whole word while the typesense engine is in use', function (): void {
    chooseSearchEngine(SearchEngine::TYPESENSE);
    $product = publishedProductNamed('Advanced Widgetry');

    $found = Product::query()->search('Widgetry')->get();

    expect($found->pluck('id')->all())->toContain($product->id);
});

it('stops using pgroonga when the extension is absent, whatever the stored engine says', function (): void {
    chooseSearchEngine(SearchEngine::PGROONGA);

    // Warm the settings cache on the real connection first: the probe below points the
    // default connection at a database with no PGroonga catalog.
    expect(app(SearchEngineService::class)->engine())->toBe(SearchEngine::PGROONGA);

    $original = DB::getDefaultConnection();
    config()->set('database.connections.no_pgroonga_extension', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);
    DB::setDefaultConnection('no_pgroonga_extension');

    try {
        // Selecting pgroonga must not put PGroonga SQL on the wire when the extension
        // cannot be reached; the deployment falls through to its database instead.
        expect(app(SearchEngineService::class)->extensionVersion())->toBeNull()
            ->and(app(SearchEngineService::class)->usesPgroonga())->toBeFalse();
    } finally {
        DB::setDefaultConnection($original);
    }
});

it('falls back to pgroonga when no engine is stored and no typesense connection exists', function (): void {
    config()->set('scout.typesense.client-settings.api_key', '');

    expect(app(SettingsService::class)->get(SettingKeyEnum::SEARCH_ENGINE))->toBeNull()
        ->and(app(SearchEngineService::class)->engine())->toBe(SearchEngine::PGROONGA);
});

it('always resolves to an engine, never an undefined state', function (): void {
    // The contract that matters for a fresh clone, a database with no settings row, or a
    // deploy that never ran `pgroonga:engine`: there is always an engine to use.
    expect(app(SettingsService::class)->get(SettingKeyEnum::SEARCH_ENGINE))->toBeNull()
        ->and(app(SearchEngineService::class)->engine())->toBeInstanceOf(SearchEngine::class);
});

it('prefers the stored engine over the configuration default', function (): void {
    config()->set('scout.typesense.client-settings.api_key', 'configured-key');
    chooseSearchEngine(SearchEngine::PGROONGA);

    expect(app(SearchEngineService::class)->engine())->toBe(SearchEngine::PGROONGA);
});

it('reports the installed extension version while pgroonga is the engine', function (): void {
    chooseSearchEngine(SearchEngine::PGROONGA);

    $engine = app(SearchEngineService::class);

    expect($engine->extensionVersion())->toBeString()
        ->and($engine->extensionVersion())->not->toBe('')
        ->and($engine->usesPgroonga())->toBeTrue();
});

it('reports no existing indexes rather than failing when the catalog cannot be read', function (): void {
    $original = DB::getDefaultConnection();
    config()->set('database.connections.no_pg_catalog', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);
    DB::setDefaultConnection('no_pg_catalog');

    try {
        // `pgroonga:status` must report drift rather than crash on a driver whose
        // catalog has no pg_index/pg_am to read.
        expect(app(SearchEngineService::class)->existingIndexes())->toBe([]);
    } finally {
        DB::setDefaultConnection($original);
    }
});

it('creates no index and runs no pgroonga DDL on a driver without pgroonga', function (): void {
    $original = DB::getDefaultConnection();
    config()->set('database.connections.no_pgroonga_driver', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);
    DB::setDefaultConnection('no_pgroonga_driver');

    try {
        $engine = app(SearchEngineService::class);

        // SQLite rejects `CREATE INDEX ... USING pgroonga` outright, so reaching the DDL
        // at all would throw. The driver guard is what keeps this a no-op.
        $engine->createIndexes();
        $engine->dropIndexes();

        expect($engine->existingIndexes())->toBe([]);
    } finally {
        DB::setDefaultConnection($original);
    }
});
