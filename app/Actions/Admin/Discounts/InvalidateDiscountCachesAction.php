<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;

/**
 * Drops the cached prices, search pages and handler registry a discount write invalidates.
 *
 * The promotion still has to be reindexed separately: bumping the tags only makes the
 * cached pages unreachable, it does not change the indexed discount-price rows.
 */
final readonly class InvalidateDiscountCachesAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(): void
    {
        $this->cache->invalidate(CacheTag::Catalog, CacheTag::Search, CacheTag::Discounts);
    }
}
