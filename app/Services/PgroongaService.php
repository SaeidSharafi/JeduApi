<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

final class PgroongaService
{
    /**
     * Check if PGroonga extension is enabled in the PostgreSQL database.
     * Caches the result to avoid repeated checks.
     *
     * @codeCoverageIgnore
     */
    public static function isPgroongaEnabled(): bool
    {
        return Cache::rememberForever('database.pgroonga_enabled', function () {
            try {
                // This query is very fast and checks the system catalog.
                $result = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'pgroonga'");

                return (bool) $result;
            } catch (QueryException $e) {
                // If the database connection fails or any other error occurs,
                // assume PGroonga is not available.
                return false;
            }
        });
    }
}
