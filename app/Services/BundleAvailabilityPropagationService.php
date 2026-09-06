<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Product\BundleReviewReasonEnum;
use App\Enums\Product\ProductableEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\ProductDeliveryOption;

final class BundleAvailabilityPropagationService
{
    /** @param array<int, int> $componentOptionIds */
    public function invalidateForComponents(array $componentOptionIds): void
    {
        $productIds = ProductDeliveryOption::query()
            ->whereIn('id', $componentOptionIds)
            ->with('bundleParents.product')
            ->get()
            ->flatMap(fn (ProductDeliveryOption $option) => $option->bundleParents->pluck('product_id'))
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return;
        }

        foreach ($productIds as $productId) {
            ProductCacheInvalidated::dispatch((int) $productId);
        }
        ProductAvailabilityCacheInvalidated::dispatch($productIds);
        ProductSearchIndexInvalidated::dispatch($productIds);
    }

    /** @param array<int, int> $componentOptionIds */
    public function requireReview(array $componentOptionIds, array $reasons): void
    {
        $parents = ProductDeliveryOption::query()
            ->whereHas('bundleComponents', fn ($query) => $query->whereIn('component_product_delivery_option_id', $componentOptionIds))
            ->get();

        $this->requireReviewForBundles($parents->modelKeys(), $reasons);
    }

    /** @param array<int, int> $bundleOptionIds */
    public function requireReviewForBundles(array $bundleOptionIds, array $reasons): void
    {
        $parents = ProductDeliveryOption::query()
            ->whereIn('id', $bundleOptionIds)
            ->get();

        foreach ($parents as $parent) {
            $parentReasons = $reasons;
            if (! $this->hasValidStoredComposition($parent)) {
                $parentReasons[] = BundleReviewReasonEnum::INVALID_COMPOSITION->value;
            }

            $parent->forceFill([
                'bundle_review_required_at' => now(),
                'bundle_review_reasons'     => collect($parent->bundle_review_reasons ?? [])
                    ->merge($parentReasons)
                    ->unique()
                    ->values()
                    ->all(),
                'composition_version' => $parent->composition_version + 1,
            ])->saveQuietly();

            ProductCacheInvalidated::dispatch((int) $parent->product_id);
            ProductAvailabilityCacheInvalidated::dispatch([(int) $parent->product_id]);
            ProductSearchIndexInvalidated::dispatch([(int) $parent->product_id]);
        }

    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<int, string>  $reasons
     */
    public function requireReviewForComponentProducts(array $productIds, array $reasons): void
    {
        $parentOptionIds = $this->parentOptionIdsForComponentProducts($productIds);

        if ($parentOptionIds === []) {
            return;
        }

        $this->requireReviewForBundles($parentOptionIds, $reasons);
    }

    /** @param array<int, int> $productIds */
    public function invalidateForComponentProducts(array $productIds): void
    {
        $parentOptionIds = $this->parentOptionIdsForComponentProducts($productIds);

        if ($parentOptionIds === []) {
            return;
        }

        $bundleProductIds = ProductDeliveryOption::query()
            ->whereIn('id', $parentOptionIds)
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();

        if ($bundleProductIds === []) {
            return;
        }

        foreach ($bundleProductIds as $productId) {
            ProductCacheInvalidated::dispatch((int) $productId);
        }
        ProductAvailabilityCacheInvalidated::dispatch($bundleProductIds);
        ProductSearchIndexInvalidated::dispatch($bundleProductIds);
    }

    private function hasValidStoredComposition(ProductDeliveryOption $bundleOption): bool
    {
        $bundleOption->loadMissing('bundleComponents.product.productable');

        if ($bundleOption->bundleComponents->isEmpty()) {
            return false;
        }

        $allowRepeated   = (bool) config('products.bundles.allow_repeated_productables', true);
        $productables    = [];
        $allocationTotal = 0;

        foreach ($bundleOption->bundleComponents as $component) {
            if ($component->product?->productable_type === ProductableEnum::BUNDLE->value) {
                return false;
            }

            $allocation = (int) $component->pivot->allocation;
            if ($allocation < 0 || $allocation > $component->price) {
                return false;
            }

            $productableKey = $component->product?->productable_type.':'.$component->product?->productable_id;
            if (! $allowRepeated && isset($productables[$productableKey])) {
                return false;
            }

            $productables[$productableKey] = true;
            $allocationTotal += $allocation;
        }

        return $allocationTotal === (int) $bundleOption->price;
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    private function parentOptionIdsForComponentProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return ProductDeliveryOption::query()
            ->whereHas('bundleComponents', fn ($query) => $query->whereIn('product_id', $productIds))
            ->pluck('id')
            ->all();
    }
}
