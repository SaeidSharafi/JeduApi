<?php

declare(strict_types=1);

namespace App\Actions\Admin\Product;

use App\Data\Admin\Product\ProductCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use SmartCache\Facades\SmartCache;

final readonly class CreateProductAction
{
    public function handle(ProductCreateData $data): Product
    {
        // Serialize publish mutations per productable so two concurrent requests
        // can never both end up with a PUBLISHED product for the same productable
        // (single published shell invariant). The partial unique index is the
        // hard DB backstop; the lock prevents the 23505 error in the happy path.
        $lockKey = "publish_productable_{$data->productable_type}_{$data->productable_id}";

        /** @var array{0: Product, 1: int[]} $result */
        [$product, $archivedProductIds] = SmartCache::lock($lockKey, 15)->block(5, function () use ($data): array {
            return DB::transaction(function () use ($data): array {
                $forceCreate      = $data->force_create ?? false;
                $productableClass = ProductableEnum::from($data->productable_type)->getModelClass();
                $productable      = $productableClass::find($data->productable_id);
                if ($forceCreate) {
                    // Archive any currently-published shell for the same productable.
                    // Captured IDs so their caches/search index are invalidated below —
                    // archiving flips them out of the published shell.
                    $archivedProductIds = Product::query()
                        ->where('productable_id', $data->productable_id)
                        ->where('productable_type', $data->productable_type)
                        ->where('status', PublicationStatusEnum::PUBLISHED)
                        ->pluck('id')
                        ->all();

                    Product::query()
                        ->whereIn('id', $archivedProductIds)
                        ->update(['status' => PublicationStatusEnum::ARCHIVED]);
                }
                $product = Product::create([
                    ...$data->except('force_create', 'categories')->toArray(),
                    'slug' => $productable->slug,
                ])->fresh();
                $product->categories()->sync($data->categories);

                return [$product, $archivedProductIds ?? []];
            });
        });

        $affectedProductIds = array_merge([$product->id], $archivedProductIds ?? []);

        ProductCacheInvalidated::dispatch($product->id);
        ProductAvailabilityCacheInvalidated::dispatch($affectedProductIds);
        ProductSearchIndexInvalidated::dispatch($affectedProductIds);

        return $product;
    }
}
