<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PgroongaService
{
    public function __construct(private readonly CacheStore $cache) {}

    /**
     * Check if PGroonga extension is enabled in the PostgreSQL database.
     * Caches the result to avoid repeated checks.
     */
    public function isPgroongaEnabled(): bool
    {
        return $this->cache->rememberForever(CacheKey::PgroongaEnabled, [], function (): bool {
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
