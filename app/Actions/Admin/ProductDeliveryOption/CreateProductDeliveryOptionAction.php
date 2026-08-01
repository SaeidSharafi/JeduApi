<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionCreateData;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\SkuGeneratorService;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductDeliveryOptionAction
{
    public function __construct(private SkuGeneratorService $skuGenerator) {}

    /**
     * Execute the action.
     */
    public function handle(ProductDeliveryOptionCreateData $data, Product $product): ProductDeliveryOption
    {
        $pdo = DB::transaction(function () use ($data, $product): ProductDeliveryOption {
            $pdoData        = $data->except('teachers')->toArray();
            $providedSku    = data_get($pdoData, 'sku');
            $pdoData['sku'] = filled($providedSku)
                ? $providedSku
                : $this->skuGenerator->generateBaseSku($data, $product);
            $pdo = $product->productDeliveryOptions()->create($pdoData)->fresh();
            $pdo->teachers()->attach($data->teachers);

            return $pdo;
        });
        ProductCacheInvalidated::dispatch($pdo->product_id);
        ProductAvailabilityCacheInvalidated::dispatch([$pdo->product_id]);
        ProductSearchIndexInvalidated::dispatch([$pdo->product_id]);

        return $pdo;
    }
}
