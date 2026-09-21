<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Product\ProductUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\BundleReviewReasonEnum;
use App\Enums\System\CacheTag;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use App\Services\BundleAvailabilityPropagationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductAction
{
    public function __construct(
        private BundleAvailabilityPropagationService $bundlePropagation,
        private CacheStore $cache,
    ) {}

    public function handle(ProductUpdateData $data, Product $product): Product
    {
        $before = $product->replicate();
        // Serialize publish-status mutations per productable so concurrent updates
        // cannot race the single-published-shell invariant. The partial unique index
        // remains the hard DB backstop.
        $lockKey = "publish_productable_{$product->productable_type}_{$product->productable_id}";

        $product = Cache::lock($lockKey, 15)->block(5, function () use ($data, $product): Product {
            return DB::transaction(function () use ($data, $product): Product {
                $product->update($data->except('categories')->toArray());
                $product->categories()->sync($data->categories);
                $product->refresh();

                return $product;
            });
        });

        ProductCacheInvalidated::dispatch($product->id);
        ProductAvailabilityCacheInvalidated::dispatch([$product->id]);
        ProductSearchIndexInvalidated::dispatch([$product->id]);

        $this->cache->invalidate(CacheTag::Catalog);

        $reasons = [];
        if ($before->term_id !== $product->term_id) {
            $reasons[] = BundleReviewReasonEnum::TERM_CHANGED->value;
        }
        if ($before->status !== $product->status && $product->status === PublicationStatusEnum::ARCHIVED) {
            $reasons[] = BundleReviewReasonEnum::COMPONENT_ARCHIVED->value;
        }

        if ($reasons !== []) {
            $this->bundlePropagation->requireReviewForComponentProducts([$product->id], $reasons);
        } else {
            $this->bundlePropagation->invalidateForComponentProducts([$product->id]);
        }

        return $product;
    }
}
