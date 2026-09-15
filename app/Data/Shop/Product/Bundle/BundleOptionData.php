<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Bundle;

use App\Models\ProductDeliveryOption;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class BundleOptionData extends Data
{
    public function __construct(
        public string $uuid,
        public string $sku,
        public ?string $name,
        public string $delivery_method,
        public string $fulfillment_type,
        public int $base_value,
        public int $selling_price,
        public int $discount_amount,
        public float $discount_percentage,
        public int $current_standalone_total,
        public bool $is_available,
        public ?int $remaining_capacity,
        public Collection $components,
    ) {}

    /** @param Collection<int, BundleComponentData> $components */
    public static function fromModel(
        ProductDeliveryOption $option,
        Collection $components,
        int $currentStandaloneTotal,
        bool $isAvailable,
        ?int $remainingCapacity,
    ): self {
        $baseValue      = (int) $components->sum('base_price');
        $sellingPrice   = (int) $option->price;
        $discountAmount = $baseValue - $sellingPrice;

        return new self(
            uuid: $option->uuid,
            sku: $option->sku,
            name: $option->name,
            delivery_method: $option->delivery_method->value,
            fulfillment_type: $option->fulfillment_type->value,
            base_value: $baseValue,
            selling_price: $sellingPrice,
            discount_amount: $discountAmount,
            discount_percentage: $baseValue > 0 ? round(($discountAmount / $baseValue) * 100, 2) : 0.0,
            current_standalone_total: $currentStandaloneTotal,
            is_available: $isAvailable,
            remaining_capacity: $remainingCapacity,
            components: $components,
        );
    }
}
