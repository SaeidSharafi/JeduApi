<?php

declare(strict_types=1);

namespace App\Enums\System;

use App\Traits\AdvanceEnum;

/**
 * The full-text search engine a deployment uses.
 *
 * This is an operator decision stored as a setting, not a per-request fallback:
 * exactly one engine serves a deployment's searches. It also decides whether the
 * five PGroonga indexes exist, because a PGroonga index makes the planner load the
 * library on every connection (~200 ms) whether or not the deployment searches.
 *
 * `pgroonga` requires the extension; `typesense` requires a configured Scout
 * Typesense connection. See {@see \App\Services\SearchEngineService}.
 */
enum SearchEngine: string
{
    use AdvanceEnum;

    case PGROONGA  = 'pgroonga';
    case TYPESENSE = 'typesense';
}
