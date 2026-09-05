<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductDeliveryOptionAction
{
    public function __construct(private SyncBundleCompositionAction $composition) {}

    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOptionUpdateData $data, ProductDeliveryOption $deliveryOption): ProductDeliveryOption
    {
        DB::transaction(function () use ($data, $deliveryOption): void {
            $pdoData = $data->except('teachers', 'components')->toArray();
            if ($deliveryOption->product?->productable_type === 'bundle') {
                $pdoData['fulfillment_type'] = 'composite';
                $pdoData['delivery_method']  = 'bundle';
                $pdoData['details_json']     = [];
            }
            $deliveryOption->update($pdoData);
            $deliveryOption->teachers()->sync($data->teachers);
            $this->composition->handle($deliveryOption, $data->components ?? []);
            if ($deliveryOption->product?->productable_type === 'bundle') {
                $deliveryOption->increment('composition_version');
            }
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

        $pdo = $deliveryOption->fresh();

        ProductCacheInvalidated::dispatch($pdo->product_id);

        if ($availabilityChanged) {
            ProductAvailabilityCacheInvalidated::dispatch([$pdo->product_id]);
            ProductSearchIndexInvalidated::dispatch([$pdo->product_id]);
        }

        return $pdo;
    }
}
