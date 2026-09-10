<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\BundlePurchaseStatusEnum;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

/**
 * Admin surface for one immutable Bundle Purchase group: aggregate derived
 * status plus every physical component with its Enrollment statuses.
 *
 * It is also the Bundle grouping entry in the unified order `items` list, where
 * `type` is always `bundle`.
 */
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
        public BundlePurchaseStatusEnum $status,
        #[DataCollectionOf(BundleComponentItemData::class)]
        public Collection $components,
        public string $type = 'bundle',
    ) {}
}
