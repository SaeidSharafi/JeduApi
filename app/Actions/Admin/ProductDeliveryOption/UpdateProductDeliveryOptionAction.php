<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\ProductDeliveryOption;
use App\Services\BundleAvailabilityPropagationService;
use App\Services\BundleAvailabilityService;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductDeliveryOptionAction
{
    public function __construct(
        private SyncBundleCompositionAction $composition,
        private BundleAvailabilityService $bundleAvailability,
        private BundleAvailabilityPropagationService $bundlePropagation,
    ) {}

    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOptionUpdateData $data, ProductDeliveryOption $deliveryOption): ProductDeliveryOption
    {
        $deliveryOption->loadMissing('bundleComponents');
        $before            = $deliveryOption->replicate();
        $beforeComposition = $deliveryOption->bundleComponents
            ->mapWithKeys(fn (ProductDeliveryOption $component): array => [
                $component->id => (int) $component->pivot->allocation,
            ])
            ->sortKeys()
            ->all();

        DB::transaction(function () use ($data, $deliveryOption): void {
            $pdoData = $data->except('teachers', 'components')->toArray();
            if ($deliveryOption->product?->productable_type === ProductableEnum::BUNDLE->value) {
                $pdoData['fulfillment_type'] = 'composite';
                $pdoData['delivery_method']  = 'bundle';
                $pdoData['details_json']     = [];
            }
            $fulfillmentType = $pdoData['fulfillment_type'] ?? $deliveryOption->fulfillment_type?->value;
            if ($fulfillmentType === FulfillmentTypeEnum::COMPOSITE->value) {
                $pdoData['is_prepayment_available'] = false;
                $pdoData['prepayment_amount']       = null;
            }
            $deliveryOption->update($pdoData);
            $deliveryOption->teachers()->sync($data->teachers);
            $this->composition->handle($deliveryOption, $data->components ?? []);
        });

        $statusChanged            = $deliveryOption->wasChanged('status');
        $indexDependenciesChanged = $deliveryOption->wasChanged([
            'fulfillment_type',
            'registration_start_date',
            'registration_end_date',
            'available_from',
            'available_to',
            'capacity',
        ]);

        // Transition-aware invalidation: any status change (DRAFT→PUBLISHED first publish,
        // PUBLISHED→ARCHIVED unpublish, ARCHIVED→PUBLISHED republish) flips availability,
        // so availability + search caches must always be invalidated, not only when the
        // option was already published before the update.
        $availabilityChanged = $statusChanged || $indexDependenciesChanged;

        $pdo              = $deliveryOption->fresh(['product', 'bundleComponents']);
        $afterComposition = $pdo->bundleComponents
            ->mapWithKeys(fn (ProductDeliveryOption $component): array => [
                $component->id => (int) $component->pivot->allocation,
            ])
            ->sortKeys()
            ->all();
        $compositionChanged = $pdo->product?->productable_type === ProductableEnum::BUNDLE->value
            && $beforeComposition !== $afterComposition;
        $materialReasons = $this->bundleAvailability->materialReasons($before, $pdo);

        if ($compositionChanged) {
            // SyncBundleCompositionAction validated-then-synced, so an invalid composition
            // cannot persist here: a valid recomposition must not archive the Bundle or
            // demand review — it only bumps the version and refreshes caches so carts
            // reconfirm. Component-driven material changes are handled below instead.
            $pdo->update(['composition_version' => $pdo->composition_version + 1]);

            ProductCacheInvalidated::dispatch($pdo->product_id);
            ProductAvailabilityCacheInvalidated::dispatch([$pdo->product_id]);
            ProductSearchIndexInvalidated::dispatch([$pdo->product_id]);

            $pdo = $pdo->fresh(['product', 'bundleComponents']);
        }

        if ($materialReasons !== [] && $pdo->bundleParents()->exists()) {
            $this->bundlePropagation->requireReview([$pdo->id], $materialReasons);
        } elseif ($pdo->bundleParents()->exists()) {
            $this->bundlePropagation->invalidateForComponents([$pdo->id]);
        }

        ProductCacheInvalidated::dispatch($pdo->product_id);

        if ($availabilityChanged) {
            ProductAvailabilityCacheInvalidated::dispatch([$pdo->product_id]);
            ProductSearchIndexInvalidated::dispatch([$pdo->product_id]);
        }

        return $pdo;
    }
}
