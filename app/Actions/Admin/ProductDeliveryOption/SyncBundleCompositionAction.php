<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Bundle;
use App\Models\ProductDeliveryOption;
use Illuminate\Validation\ValidationException;

final readonly class SyncBundleCompositionAction
{
    /** @param array<int, array<string, mixed>> $components */
    public function handle(ProductDeliveryOption $bundleOption, array $components): void
    {
        $productable = $bundleOption->product?->productable;
        if (! $productable instanceof Bundle) {
            if ($components !== []) {
                throw ValidationException::withMessages(['components' => 'Components are only valid for Bundle products.']);
            }

            return;
        }

        $this->validate($bundleOption, $components);

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

    /** @param array<int, array<string, mixed>> $components */
    private function validate(ProductDeliveryOption $bundleOption, array $components): void
    {
        $componentIds = collect($components)->map(fn (array $component): ?int => $this->resolveComponentId($component));
        if ($componentIds->contains(null) || $componentIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['components' => 'Components must contain unique existing delivery options.']);
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
                throw ValidationException::withMessages(['components' => 'Every component must reference an existing non-nested delivery option.']);
            }
            if ($option->product?->productable instanceof Bundle) {
                throw ValidationException::withMessages(['components' => 'Nested Bundle components are not allowed.']);
            }
            $allocation = (int) $component['allocation'];
            if ($allocation < 0 || $allocation > $option->price) {
                throw ValidationException::withMessages(['components' => 'An allocation cannot exceed the component base price.']);
            }
            $key = $option->product?->productable_type.':'.$option->product?->productable_id;
            if (! $allowRepeated && isset($productables[$key])) {
                throw ValidationException::withMessages(['components' => 'Repeated Productables are not allowed in this Bundle.']);
            }
            $productables[$key] = true;
            $allocationTotal += $allocation;
            if ($bundleOption->status === PublicationStatusEnum::PUBLISHED && (
                $option->status                            !== PublicationStatusEnum::PUBLISHED
                || $option->product?->status               !== PublicationStatusEnum::PUBLISHED
                || $option->product?->productable?->status !== PublicationStatusEnum::PUBLISHED
            )) {
                throw ValidationException::withMessages(['status' => 'A published Bundle requires published components.']);
            }
        }

        if ($allocationTotal !== (int) $bundleOption->price) {
            throw ValidationException::withMessages(['components' => 'Component allocations must equal the Bundle price.']);
        }
    }

    /** @param array<string, mixed> $component */
    private function resolveComponentId(array $component): ?int
    {
        if (isset($component['product_delivery_option_id'])) {
            return (int) $component['product_delivery_option_id'];
        }

        if (isset($component['product_delivery_option_uuid'])) {
            $id = ProductDeliveryOption::query()
                ->where('uuid', $component['product_delivery_option_uuid'])
                ->value('id');

            return $id === null ? null : (int) $id;
        }

        return null;
    }
}
