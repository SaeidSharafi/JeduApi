<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Contracts\Cache\CacheStore;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\System\CacheTag;
use App\Jobs\Discounts\RegeneratePromotionDiscountPricesJob;
use App\Models\DiscountPromotion;

/**
 * Toggles a promotion's active flag and makes the price change visible immediately.
 *
 * Clearing the caches alone is not enough: the indexed discount-price rows still hold
 * the old promotion. The reindex therefore runs synchronously before the caches are
 * dropped, so a request in between cannot re-cache the pre-toggle price.
 */
final readonly class UpdateDiscountPromotionStatusAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(DiscountPromotion $promotion): DiscountPromotion
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        if ($promotion->type === DiscountTypeEnum::PRODUCT_SPECIFIC) {
            RegeneratePromotionDiscountPricesJob::dispatchSync($promotion);
        }

        $this->cache->invalidate(CacheTag::Catalog, CacheTag::Search, CacheTag::Discounts);

        return $promotion->fresh();
    }
}
