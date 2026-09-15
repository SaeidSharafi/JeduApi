<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Enums\Product\ProductableEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\ProductDeliveryOption;
use App\Services\BundleAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReviewBundleAction
{
    public function __construct(
        private ValidateBundleCompositionAction $validator,
        private BundleAvailabilityService $availability,
    ) {}

    public function handle(ProductDeliveryOption $bundleOption): ProductDeliveryOption
    {
        if ($bundleOption->product?->productable_type !== ProductableEnum::BUNDLE->value) {
            throw ValidationException::withMessages([
                'delivery_option' => [__('messages.product.bundle_review_only_for_bundles')],
            ]);
        }

        $bundleOption->load('bundleComponents');
        $components = $bundleOption->bundleComponents->map(
            fn (ProductDeliveryOption $component): array => [
                'product_delivery_option_id' => $component->id,
                'allocation'                 => $component->pivot->allocation,
            ],
        )->all();

        $this->validator->handle($bundleOption, $components);
        $this->availability->validateReview($bundleOption);

        $bundleOption = DB::transaction(function () use ($bundleOption): ProductDeliveryOption {
            $bundleOption->forceFill([
                'bundle_review_required_at' => null,
                'bundle_review_reasons'     => null,
                'composition_version'       => $bundleOption->composition_version + 1,
            ])->save();

            return $bundleOption->fresh();
        });

        ProductCacheInvalidated::dispatch($bundleOption->product_id);
        ProductAvailabilityCacheInvalidated::dispatch([$bundleOption->product_id]);
        ProductSearchIndexInvalidated::dispatch([$bundleOption->product_id]);

        return $bundleOption;
    }
}
