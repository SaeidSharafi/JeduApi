<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Exceptions\BundleCompositionValidationException;
use App\Models\Bundle;
use App\Models\ProductDeliveryOption;

final readonly class SyncBundleCompositionAction
{
    public function __construct(
        private ValidateBundleCompositionAction $validator,
    ) {}

    /** @param array<int, array<string, mixed>> $components */
    public function handle(ProductDeliveryOption $bundleOption, array $components): void
    {
        $bundleName  = $bundleOption->product?->name;
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

        $this->validator->handle($bundleOption, $components, $bundleName);

        $sync = [];
        foreach ($components as $component) {
            $id = $component['product_delivery_option_id'] ?? null;
            if ($id === null && isset($component['product_delivery_option_uuid'])) {
                $id = ProductDeliveryOption::query()->where('uuid', $component['product_delivery_option_uuid'])->value('id');
            }
            $sync[(int) $id] = ['allocation' => (int) $component['allocation']];
        }

        $bundleOption->bundleComponents()->sync($sync);
    }
}
