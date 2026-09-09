<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\OrderItemStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * One physical component line beneath an admin Bundle Purchase response.
 * Exposes the immutable pricing snapshot and the live Enrollment statuses so
 * support agents see provisioning health per component.
 */
final class BundleComponentItemData extends Data
{
    public function __construct(
        public int $id,
        public int $product_delivery_option_id,
        public string $name,
        public string $sku,
        public int $price,
        public int $total,
        public int $paid_amount,
        public int $total_discount_amount,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public OrderItemStatusEnum $status,
        public ?BundleEnrollmentStatusData $enrollment = null,
    ) {}
}
