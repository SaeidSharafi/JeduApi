<?php

declare(strict_types=1);

namespace App\Actions\Admin\Partner;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;

/**
 * Drops the shop partner listings a partner write invalidates.
 */
final readonly class ForgetPartnerCachesAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(): void
    {
        $this->cache->forget(CacheKey::PartnersInHome);
        $this->cache->forget(CacheKey::PartnersInCourse);
        $this->cache->forget(CacheKey::Partners);
    }
}
