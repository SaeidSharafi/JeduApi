<?php

declare(strict_types=1);

use App\Console\Commands\SetSearchEngineCommand;
use App\Enums\System\SearchEngine;
use App\Enums\System\SettingKeyEnum;
use App\Services\SearchEngineService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;

mutates(SetSearchEngineCommand::class);

/**
 * The command is asserted through what it stores and what it refuses — not through the
 * index DDL it triggers. That DDL is verified against a real environment, because
 * `CREATE INDEX` / `DROP INDEX` on the five shared tables deadlocks against the schema
 * the shared TestCase tears down when the suite runs with `--parallel` — which is how CI
 * runs. Pinning it here would mean a test that cannot run in CI.
 */
it('rejects an engine it does not know and writes nothing', function (): void {
    $this->artisan('pgroonga:engine', ['engine' => 'elasticsearch'])
        ->expectsOutputToContain('Unknown search engine [elasticsearch]')
        ->assertExitCode(1);

    // The engine resolves to a default either way, so "unchanged" means no setting was
    // written rather than a particular engine value.
    expect(app(SettingsService::class)->get(SettingKeyEnum::SEARCH_ENGINE))->toBeNull();
});

it('stores the chosen engine so later reads see it', function (): void {
    $this->artisan('pgroonga:engine', ['engine' => 'typesense'])
        ->expectsOutputToContain('Search engine set to [typesense]')
        ->assertExitCode(0);

    expect(app(SearchEngineService::class)->engine())->toBe(SearchEngine::TYPESENSE)
        ->and(app(SettingsService::class)->get(SettingKeyEnum::SEARCH_ENGINE))->toBe(SearchEngine::TYPESENSE->value);
});

it('warns but still switches when typesense has no configured key', function (): void {
    config()->set('scout.typesense.client-settings.api_key', '');

    $this->artisan('pgroonga:engine', ['engine' => 'typesense'])
        ->expectsOutputToContain('no configured connection')
        ->assertExitCode(0);

    expect(app(SearchEngineService::class)->engine())->toBe(SearchEngine::TYPESENSE);
});

it('still switches to typesense when a key is configured', function (): void {
    // The warning branch cannot be avoided from the test environment (no reachable
    // Typesense instance), so this asserts only that a configured key does not block the
    // switch. Nothing here asserts how Typesense itself behaves.
    config()->set('scout.typesense.client-settings.api_key', 'configured-key');

    $this->artisan('pgroonga:engine', ['engine' => 'typesense'])->assertExitCode(0);

    expect(app(SearchEngineService::class)->engine())->toBe(SearchEngine::TYPESENSE);
});

it('refuses the pgroonga engine when the extension is not installed, changing nothing', function (): void {
    // Warm the settings cache on the real connection first: the probe below points the
    // default connection at a database with no pg_extension catalog.
    app(SettingsService::class)->get(SettingKeyEnum::SEARCH_ENGINE);

    $original = DB::getDefaultConnection();
    config()->set('database.connections.no_pgroonga_extension', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);
    DB::setDefaultConnection('no_pgroonga_extension');

    try {
        $this->artisan('pgroonga:engine', ['engine' => 'pgroonga'])
            ->expectsOutputToContain('the PGroonga extension is not installed')
            ->assertExitCode(1);
    } finally {
        DB::setDefaultConnection($original);
    }

    expect(app(SettingsService::class)->get(SettingKeyEnum::SEARCH_ENGINE))->toBeNull();
});
