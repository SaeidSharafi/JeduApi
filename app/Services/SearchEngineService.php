<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\System\SearchEngine;
use App\Enums\System\SettingKeyEnum;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single answer to "which full-text search engine does this deployment use?".
 *
 * The engine is an operator decision persisted as a setting, so it survives a
 * deploy and can be changed afterwards without shipping code. It is deliberately
 * not a runtime fallback: `pgroonga` and `typesense` are two mutually exclusive
 * choices, and nothing here switches engines because the chosen one misbehaves.
 *
 * The stored engine also owns the five PGroonga indexes, because the cost those
 * indexes impose is paid by the planner on every connection that plans against an
 * indexed table whether or not the deployment searches. {@see self::indexes()} is
 * their single definition, reconciled by `pgroonga:engine`.
 */
final class SearchEngineService
{
    /**
     * The five PGroonga indexes, keyed by the table they serve.
     *
     * Plain indexes, deliberately not partial: a predicate on the index cannot
     * avoid the library load, because the planner resolves every index's access
     * method handler before it evaluates any predicate.
     *
     * @var array<string, array{name: string, columns: list<string>}>
     */
    private const array PGROONGA_INDEXES = [
        'products' => [
            'name'    => 'products_pgroonga_index',
            'columns' => ['name', 'short_name', 'short_description', 'slug'],
        ],
        'courses' => [
            'name'    => 'courses_pgroonga_index',
            'columns' => ['full_name', 'short_name', 'slug', 'description'],
        ],
        'seminars' => [
            'name'    => 'seminars_pgroonga_index',
            'columns' => ['full_name', 'short_name', 'slug', 'description', 'keywords'],
        ],
        'digital_assets' => [
            'name'    => 'digital_assets_pgroonga_index',
            'columns' => ['full_name', 'short_name', 'slug', 'description', 'keywords'],
        ],
        'blog_posts' => [
            'name'    => 'blog_posts_pgroonga_index',
            'columns' => ['title', 'body', 'excerpt', 'slug'],
        ],
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * The engine this deployment uses.
     *
     * Never null: a deployment always has an engine. The stored setting wins when it
     * exists, and otherwise the engine is derived from the deployment's Scout
     * configuration — Typesense when a connection is configured, PGroonga when it is
     * not. That default is what makes a fresh clone, a database with no settings row, or
     * a deploy that never ran `pgroonga:engine` behave sensibly rather than falling into
     * an undefined state.
     */
    public function engine(): SearchEngine
    {
        $stored = $this->settings->get(SettingKeyEnum::SEARCH_ENGINE);

        if (is_string($stored) && ($engine = SearchEngine::tryFrom($stored)) !== null) {
            return $engine;
        }

        return $this->typesenseIsConfigured() ? SearchEngine::TYPESENSE : SearchEngine::PGROONGA;
    }

    /**
     * Whether the PGroonga query path may run.
     *
     * Requires both the chosen engine and the installed extension. The extension
     * half is a misconfiguration guard, not detection: an operator who selects
     * PGroonga on a database without it gets the non-PGroonga path instead of
     * broken SQL.
     */
    public function usesPgroonga(): bool
    {
        return $this->engine() === SearchEngine::PGROONGA && $this->extensionIsInstalled();
    }

    /**
     * The installed PGroonga extension version, or null when it is not installed.
     */
    public function extensionVersion(): ?string
    {
        try {
            $version = DB::selectOne(
                "SELECT extversion FROM pg_extension WHERE extname = 'pgroonga'"
            );
        } catch (QueryException) {
            return null;
        }

        return $version === null ? null : (string) $version->extversion;
    }

    /**
     * The table and index names this engine choice owns.
     *
     * @return array<string, array{name: string, columns: list<string>}>
     */
    public function indexes(): array
    {
        return self::PGROONGA_INDEXES;
    }

    /**
     * Persist the engine this deployment uses.
     *
     * Authoritative: the caller's argument wins, including on a database whose engine
     * setting does not exist yet (after a fresh migration, for instance).
     */
    public function setEngine(SearchEngine $engine): void
    {
        $this->settings->set(SettingKeyEnum::SEARCH_ENGINE, $engine->value, 'string', 'search');
    }

    /**
     * Whether this environment can search with Typesense.
     *
     * A configured API key in a real environment, but never during the test suite: the
     * suite carries a key in its environment without a Typesense instance to talk to, so
     * treating that as configured would route every search through a connection that
     * cannot exist. A test that wants the Typesense engine stores it explicitly.
     */
    public function typesenseIsConfigured(): bool
    {
        return ! app()->runningUnitTests()
            && ! empty(config('scout.typesense.client-settings.api_key'));
    }

    /**
     * Create every PGroonga index that does not exist yet.
     *
     * Idempotent: `CREATE INDEX IF NOT EXISTS` is a no-op for an index that is
     * already present. Silently does nothing on a driver that has no PGroonga.
     */
    public function createIndexes(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        foreach (self::PGROONGA_INDEXES as $table => $index) {
            $columns = implode(', ', $index['columns']);

            // The identifiers come from the private PGROONGA_INDEXES constant, never
            // from input, so there is nothing to bind.
            DB::unprepared(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON %s USING pgroonga (%s);',
                $index['name'],
                $table,
                $columns,
            ));
        }
    }

    /**
     * Drop every PGroonga index, whether or not it was created by this application.
     *
     * Idempotent: `DROP INDEX IF EXISTS` is a no-op for an index already gone.
     * Silently does nothing on a driver that has no PGroonga.
     */
    public function dropIndexes(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        foreach (self::PGROONGA_INDEXES as $index) {
            DB::unprepared(sprintf('DROP INDEX IF EXISTS %s;', $index['name']));
        }
    }

    /**
     * The PGroonga indexes that exist in the database right now.
     *
     * @return list<string>
     */
    public function existingIndexes(): array
    {
        $names = array_column(self::PGROONGA_INDEXES, 'name');

        try {
            $rows = DB::select(
                'SELECT c.relname AS name
                   FROM pg_index i
                   JOIN pg_class c ON c.oid = i.indexrelid
                   JOIN pg_am a ON a.oid = c.relam
                  WHERE a.amname = ? AND c.relname = ANY(?)',
                ['pgroonga', '{'.implode(',', $names).'}'],
            );
        } catch (QueryException) {
            return [];
        }

        return array_map(static fn (object $row): string => (string) $row->name, $rows);
    }

    private function isPostgres(): bool
    {
        try {
            return DB::connection()->getDriverName() === 'pgsql';
        } catch (QueryException) {
            return false;
        }
    }

    private function extensionIsInstalled(): bool
    {
        try {
            return DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'pgroonga'") !== null;
        } catch (QueryException) {
            // A driver without the catalog (or a failed connection) has no PGroonga.
            return false;
        }
    }
}
