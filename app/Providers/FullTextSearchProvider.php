<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\PgroongaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class FullTextSearchProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @codeCoverageIgnore
     */
    public function boot(): void
    {
        Builder::macro('withPgroonga', function () {
            if ($this->getConnection()->getDriverName() === 'pgsql') {
                $this->whereRaw('use_pgroonga()');
            }
            return $this;
        });

        /**
         * Perform a full-text search with automatic driver detection.
         *
         * Usage:
         *   ->fullTextSearch('name', 'Laravel')
         *   ->fullTextSearch(['name', 'description'], 'Laravel framework')
         *   ->fullTextSearch(['title', 'body'], 'search term', scoreAs: 'relevance')
         *
         * @param  array|string  $columns  Column(s) to search
         * @param  string  $value  Search term
         * @param  string|null  $scoreAs  Optional alias for the score column (enables scoring)
         */
        Builder::macro('fullTextSearch', function (array|string $columns, string $value, ?string $scoreAs = null) {
            /** @var Builder $this */
            $driver           = config('database.default');
            $connectionConfig = config("database.connections.{$driver}");
            $dbDriver         = $connectionConfig['driver'] ?? 'mysql';
            $columns          = (array) $columns;

            // PostgreSQL with PGroonga
            if ($dbDriver === 'pgsql' && PgroongaService::isPgroongaEnabled()) {
                if (count($columns) === 1) {
                    $this->whereRaw("\"{$columns[0]}\" &@~ ?", [$value]);
                } else {
                    $this->where(function ($query) use ($columns, $value): void {
                        foreach ($columns as $column) {
                            $query->orWhereRaw("\"{$column}\" &@~ ?", [$value]);
                        }
                    });
                }

                // Add score if requested
                if ($scoreAs) {
                    $this->selectRaw("pgroonga_score(tableoid, ctid) as \"{$scoreAs}\"");
                }

                return $this;
            }

            // MySQL with FULLTEXT index
            if ($dbDriver === 'mysql') {
                $columnList = implode(', ', array_map(fn ($col): string => "`{$col}`", $columns));
                $this->whereRaw("MATCH({$columnList}) AGAINST(? IN BOOLEAN MODE)", [$value]);

                if ($scoreAs) {
                    $this->selectRaw("MATCH({$columnList}) AGAINST(? IN BOOLEAN MODE) as `{$scoreAs}`", [$value]);
                }

                return $this;
            }

            // PostgreSQL without PGroonga (built-in full-text search)
            if ($dbDriver === 'pgsql') {
                if (count($columns) === 1) {
                    $this->whereFullText($columns[0], $value);
                } else {
                    $this->whereFullText($columns, $value);
                }

                // Note: PostgreSQL full-text search scoring requires ts_rank which is more complex
                // and requires tsvector columns. Not implemented in this fallback.
                return $this;
            }

            // SQLite fallback: Use LIKE for each column (no scoring support)
            $this->where(function ($query) use ($columns, $value): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'LIKE', "%{$value}%");
                }
            });

            return $this;
        });

        /**
         * Add an OR full-text search condition.
         *
         * Usage:
         *   ->where('status', 'published')
         *   ->orFullTextSearch('name', 'Laravel')
         */
        Builder::macro('orFullTextSearch', function (array|string $columns, string $value) {
            /** @var Builder $this */
            return $this->orWhere(function ($query) use ($columns, $value): void {
                $query->fullTextSearch($columns, $value);
            });
        });

        /**
         * Order by full-text search relevance score.
         *
         * This should be called AFTER fullTextSearch() with a scoreAs parameter.
         *
         * Usage:
         *   ->fullTextSearch(['name', 'description'], 'Laravel', scoreAs: 'relevance')
         *   ->orderByScore('relevance', 'desc')
         *
         * @param  string  $scoreColumn  The score column alias (must match the scoreAs parameter)
         * @param  string  $direction  'asc' or 'desc'
         */
        Builder::macro('orderByScore', function (string $scoreColumn = 'score', string $direction = 'desc') {
            /** @var Builder $this */
            $driver           = config('database.default');
            $connectionConfig = config("database.connections.{$driver}");
            $dbDriver         = $connectionConfig['driver'] ?? 'mysql';

            if ($dbDriver === 'pgsql' && PgroongaService::isPgroongaEnabled()) {
                return $this->orderBy($scoreColumn, $direction);
            }

            return $this;
        });
        Builder::macro('selectScore', function (string $scoreColumn = 'score', string $table = '') {
            /** @var Builder $this */
            $driver           = config('database.default');
            $connectionConfig = config("database.connections.{$driver}");
            $dbDriver         = $connectionConfig['driver'] ?? 'mysql';

            if ($dbDriver === 'pgsql' && PgroongaService::isPgroongaEnabled()) {
                $table = $table ? "{$table}." : '';

                return $this->addSelect(DB::raw("pgroonga_score({$table}tableoid, {$table}ctid) as {$scoreColumn}"));
            }

            return $this;
        });
    }
}
