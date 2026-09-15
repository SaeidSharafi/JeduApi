<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\OrderItem;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class BundleComponentOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public int $product_delivery_option_id,
        public string $name,
        public string $sku,
        public int $price,
        public int $paid_amount,
        public int $bundle_discount_amount,
        #[WithTransformer(TranslatableEnumData::class)]
        public OrderItemStatusEnum $status,
        public int $enrollment_id,
        public string $enrollment_uuid,
        #[WithTransformer(TranslatableEnumData::class)]
        public EnrollmentStatusEnum $enrollment_status,
        #[WithTransformer(TranslatableEnumData::class)]
        public ProvisioningStatusEnum $provisioning_status,
    ) {}

    public static function fromModel(OrderItem $item): self
    {
        return new self(
            id: $item->id,
            product_delivery_option_id: $item->product_delivery_option_id,
            name: $item->name,
            sku: $item->sku,
            price: $item->price,
            paid_amount: $item->paid_amount,
            bundle_discount_amount: $item->pricing_metadata['bundle_discount_amount'],
            status: $item->status,
            enrollment_id: $item->enrollment->id,
            enrollment_uuid: $item->enrollment->uuid,
            enrollment_status: $item->enrollment->enrollment_status,
            provisioning_status: $item->enrollment->provisioning_status,
        );
    }
}
