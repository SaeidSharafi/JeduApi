<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionCreateData;
use App\Events\ProductCacheInvalidated;
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
            $baseSku = $this->skuGenerator->generateBaseSku($data, $product);
            $pdoData = $data->except('teachers')->toArray();
            $pdoData['sku'] = data_get($pdoData,'sku') ?? $baseSku;
            $pdo     = $product->productDeliveryOptions()->create($pdoData)->fresh();
            $pdo->teachers()->attach($data->teachers);

            return $pdo;
        });
        ProductCacheInvalidated::dispatch($pdo->product_id);

        return $pdo;
    }
}
