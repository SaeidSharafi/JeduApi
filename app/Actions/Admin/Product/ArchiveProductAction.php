<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Enums\Content\PublicationStatusEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveProductAction
{
    /**
     * Archive a product and invalidate all dependent caches.
     *
     * Archiving flips the product out of the published shell, so availability
     * and search caches MUST be invalidated (previously the controller updated
     * status directly and skipped every invalidation event).
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

        return $product;
    }
}
