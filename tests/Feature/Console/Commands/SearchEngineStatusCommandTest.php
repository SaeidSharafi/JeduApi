<?php

declare(strict_types=1);

use App\Console\Commands\SearchEngineStatusCommand;
use App\Enums\System\SearchEngine;
use App\Enums\System\SettingKeyEnum;
use App\Services\SettingsService;

mutates(SearchEngineStatusCommand::class);

/**
 * Status always reports an engine, because a deployment always has one: the stored
 * setting when present, otherwise the engine derived from the Scout configuration.
 *
 * Assertions target the report table rather than the long trailing error string, which
 * Laravel wraps across lines and so is not reliably matchable as a substring.
 */
it('reports the resolved default when no engine is stored', function (): void {
    // No Typesense connection, so the configuration default is pgroonga. With no indexes
    // present that is drift, and drift is reported as a failure rather than silence.
    config()->set('scout.typesense.client-settings.api_key', '');

    expect(app(SettingsService::class)->get(SettingKeyEnum::SEARCH_ENGINE))->toBeNull();

    $this->artisan('pgroonga:status')
        ->expectsOutputToContain('pgroonga')
        ->expectsOutputToContain('0 of 5')
        ->assertExitCode(1);
});

it('reports the stored engine over the configuration default', function (): void {
    // Typesense is configured, so the default would be typesense; the stored pgroonga wins.
    config()->set('scout.typesense.client-settings.api_key', 'configured-key');
    app(SettingsService::class)->set(SettingKeyEnum::SEARCH_ENGINE, SearchEngine::PGROONGA->value, 'string', 'search');

    $this->artisan('pgroonga:status')
        ->expectsOutputToContain('pgroonga')
        ->expectsOutputToContain('0 of 5')
        ->assertExitCode(1);
});

it('reports typesense as the engine when that is what is stored', function (): void {
    app(SettingsService::class)->set(SettingKeyEnum::SEARCH_ENGINE, SearchEngine::TYPESENSE->value, 'string', 'search');

    // Typesense with no PGroonga indexes is the expected state, so this is a match.
    $this->artisan('pgroonga:status')
        ->expectsOutputToContain('typesense')
        ->expectsOutputToContain('0 of 5')
        ->assertExitCode(0);
});
