<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\BundleReviewReasonEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use App\Services\BundleAvailabilityPropagationService;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveProductAction
{
    public function __construct(private BundleAvailabilityPropagationService $bundlePropagation) {}

    /**
     * Archive the product, invalidate its caches, and require review of any
     * Bundle using it.
     */
    public function handle(Product $product): Product
    {
        $product = DB::transaction(function () use ($product): Product {
            $product->update(['status' => PublicationStatusEnum::ARCHIVED]);

            return $product->fresh();
        });

        ProductCacheInvalidated::dispatch($product->id);
        ProductAvailabilityCacheInvalidated::dispatch([$product->id]);
        ProductSearchIndexInvalidated::dispatch([$product->id]);

        $this->bundlePropagation->requireReviewForComponentProducts(
            [$product->id],
            [BundleReviewReasonEnum::COMPONENT_ARCHIVED->value],
        );

        return $product;
    }
}
