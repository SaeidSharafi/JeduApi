<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Enums\Content\PublicationStatusEnum;
use App\Exceptions\BundleCompositionValidationException;
use App\Models\Bundle;
use App\Models\ProductDeliveryOption;

final readonly class ValidateBundleCompositionAction
{
    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    public function handle(ProductDeliveryOption $bundleOption, array $components, ?string $bundleName = null): void
    {
        $bundleName ??= $bundleOption->product?->name;
        $productable = $bundleOption->product?->productable;
        if (! $productable instanceof Bundle) {
            if ($components !== []) {
                throw new BundleCompositionValidationException(
                    __('messages.product.bundle_components_only_for_bundle'),
                    bundleName: $bundleName,
                );
            }

            return;
        }

        $componentIds = collect($components)
            ->pluck('product_delivery_option_id')
            ->map(fn (mixed $id): int => (int) $id);
        if ($componentIds->contains(0) || $componentIds->duplicates()->isNotEmpty()) {
            throw new BundleCompositionValidationException(
                __('messages.product.bundle_components_unique_required'),
                bundleName: $bundleName,
            );
        }

        $options = ProductDeliveryOption::query()
            ->with('product.productable')
            ->whereIn('id', $componentIds->filter()->all())
            ->get()
            ->keyBy('id');

        $allowRepeated   = (bool) config('products.bundles.allow_repeated_productables', true);
        $productables    = [];
        $allocationTotal = 0;

        foreach ($components as $index => $component) {
            $id     = (int) $componentIds[$index];
            $option = $options->get($id);

            if (! $option || $option->id === $bundleOption->id) {
                throw new BundleCompositionValidationException(
                    __('messages.product.bundle_component_reference_invalid'),
                    bundleName: $bundleName,
                );
            }

            if ($option->product?->productable instanceof Bundle) {
                throw new BundleCompositionValidationException(
                    __('messages.product.bundle_component_nested_invalid'),
                    bundleName: $bundleName,
                );
            }

            $allocation = (int) $component['allocation'];
            if ($allocation < 0 || $allocation > $option->price) {
                throw new BundleCompositionValidationException(
                    __('messages.product.bundle_component_allocation_invalid'),
                    bundleName: $bundleName,
                );
            }

            $key = $option->product?->productable_type.':'.$option->product?->productable_id;
            if (! $allowRepeated && isset($productables[$key])) {
                throw new BundleCompositionValidationException(
                    __('messages.product.bundle_component_repeated_invalid'),
                    bundleName: $bundleName,
                );
            }

            $productables[$key] = true;
            $allocationTotal += $allocation;

            if ($bundleOption->status === PublicationStatusEnum::PUBLISHED && (
                $option->status                            !== PublicationStatusEnum::PUBLISHED
                || $option->product?->status               !== PublicationStatusEnum::PUBLISHED
                || $option->product?->productable?->status !== PublicationStatusEnum::PUBLISHED
            )) {
                throw new BundleCompositionValidationException(
                    __('messages.product.bundle_component_status_invalid'),
                    bundleName: $bundleName,
                );
            }
        }

        if ($allocationTotal !== (int) $bundleOption->price) {
            throw new BundleCompositionValidationException(
                __('messages.product.bundle_component_total_invalid'),
                bundleName: $bundleName,
            );
        }
    }
}
