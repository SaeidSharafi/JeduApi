<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\OrderStatusEnum;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class BundlePurchaseData extends Data
{
    public function __construct(
        public int $id,
        public int $product_delivery_option_id,
        public string $bundle_name,
        public string $product_name,
        public string $name,
        public string $sku,
        public int $base_value,
        public int $selling_price,
        public int $composition_version,
        #[WithTransformer(TranslatableEnumData::class)]
        public OrderStatusEnum $status,
        #[DataCollectionOf(BundleComponentOrderItemData::class)]
        public Collection $components,
    ) {}
}
