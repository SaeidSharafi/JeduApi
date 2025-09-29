<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Data\Shop\ProductDeliveryOptionPriceData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\ProductDeliveryOption;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class ProductDeliveryOptionData extends Data
{
    public function __construct(
        public string $uuid,
        public string $sku,
        public ?string $name,
        public ProductDeliveryOptionPriceData $price_data,
        #[WithTransformer(TranslatableEnumData::class)]
        public FulfillmentTypeEnum $fulfillment_type,
        #[WithTransformer(TranslatableEnumData::class)]
        public DeliveryMethodEnum $delivery_method,

    ) {}

    public static function fromModel(ProductDeliveryOption $deliveryOption, ProductDeliveryOptionPriceData $priceData): self
    {
        return new self(
            uuid: $deliveryOption->uuid,
            sku: $deliveryOption->sku,
            name: $deliveryOption->name,
            price_data: $priceData,
            fulfillment_type: $deliveryOption->fulfillment_type,
            delivery_method: $deliveryOption->delivery_method,
        );
    }
}
