<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProductDeliveryOptionAction
{
    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOptionUpdateData $data, ProductDeliveryOption $deliveryOption): ProductDeliveryOption
    {
        $wasPublished = $deliveryOption->status === PublicationStatusEnum::PUBLISHED;

        DB::transaction(function () use ($data, $deliveryOption): void {
            $pdoData = $data->except('teachers')->toArray();
            $deliveryOption->update($pdoData);
            $deliveryOption->teachers()->sync($data->teachers);
        });

        $indexDependenciesChanged = $deliveryOption->wasChanged([
            'status',
            'fulfillment_type',
            'registration_start_date',
            'registration_end_date',
            'available_from',
            'available_to',
            'capacity',
        ]);
        $pdo = $deliveryOption->fresh();

        ProductCacheInvalidated::dispatch($pdo->product_id);

        if ($wasPublished && $indexDependenciesChanged) {
            ProductAvailabilityCacheInvalidated::dispatch([$pdo->product_id]);
            ProductSearchIndexInvalidated::dispatch([$pdo->product_id]);
        }

        return $pdo;
    }
}
