<?php

declare(strict_types=1);

namespace App\Enums\System;

use App\Traits\AdvanceEnum;

/**
 * The invalidation vocabulary for the cache layer.
 *
 * Every {@see CacheKey} declares exactly one tag, and a write path invalidates
 * the tags it disturbed. Group invalidation is a version counter per tag, so
 * bumping a tag makes the previous generation of its keys unreachable without
 * scanning or tagging the underlying store.
 */
enum CacheTag: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case HomePage  = 'home_page';
    case Content   = 'content';
    case Catalog   = 'catalog';
    case Search    = 'search';
    case Discounts = 'discounts';
    case Settings  = 'settings';
    case Auth      = 'auth';
}
