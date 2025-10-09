<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PgroongaService
{
    public static function isPgroongaEnabled(): bool
    {
        return Cache::rememberForever('database.pgroonga_enabled', function () {
            try {
                // This query is very fast and checks the system catalog.
                $result = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'pgroonga'");

                return (bool) $result;
            } catch (Throwable $e) {
                // If the database connection fails or any other error occurs,
                // assume PGroonga is not available.
                return false;
            }
        });
    }
}
