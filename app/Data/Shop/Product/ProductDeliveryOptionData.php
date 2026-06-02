<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Data\Shop\ProductDeliveryOptionPriceData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\ProductDeliveryOption;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

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
        public bool $is_available,
        public bool $is_purchasable,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $available_from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $available_to,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $registration_start_date,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $registration_end_date,

    ) {}

    public static function fromModel(ProductDeliveryOption $deliveryOption, ProductDeliveryOptionPriceData $priceData): self
    {
        $isAvailable   = self::isAvailable($deliveryOption->available_from, $deliveryOption->available_to);
        $isPurchasable = $isAvailable && self::isAvailable($deliveryOption->registration_start_date, $deliveryOption->registration_end_date);

        return new self(
            uuid: $deliveryOption->uuid,
            sku: $deliveryOption->sku,
            name: $deliveryOption->name,
            price_data: $priceData,
            fulfillment_type: $deliveryOption->fulfillment_type,
            delivery_method: $deliveryOption->delivery_method,
            is_available: $isAvailable,
            is_purchasable: $isPurchasable,
            available_from: $deliveryOption->available_from ? verta($deliveryOption->available_from) : null,
            available_to: $deliveryOption->available_to ? verta($deliveryOption->available_to) : null,
            registration_start_date: $deliveryOption->registration_start_date ? verta($deliveryOption->registration_start_date) : null,
            registration_end_date: $deliveryOption->registration_end_date ? verta($deliveryOption->registration_end_date) : null,
        );
    }

    private static function isAvailable(
        null|\Carbon\Carbon|\Carbon\CarbonImmutable $availableFrom,
        null|\Carbon\Carbon|\Carbon\CarbonImmutable $availableTo
    ): bool {
        if ($availableFrom === null && $availableTo === null) {
            return true;
        }

        if ($availableTo && $availableFrom === null) {
            return now()->lessThanOrEqualTo($availableTo);
        }

        if ($availableFrom && $availableTo === null) {
            return now()->greaterThanOrEqualTo($availableFrom);
        }

        return now()->between($availableFrom, $availableTo);
    }
}
