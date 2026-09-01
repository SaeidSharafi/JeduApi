<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Data\Shop\ProductDeliveryOptionPriceData;
use App\Enums\MediaTagEnum;
use App\Models\ProductDeliveryOption;
use Spatie\LaravelData\Data;

final class ProductDeliveryOptionCardData extends Data
{
    public function __construct(
        public string $uuid,
        public string $slug,
        public ?string $name,
        public ?string $short_name,
        public ?string $short_description,
        public ?string $vendor,
        public ?string $term,
        public int $price,
        public ?int $prepayment_amount = null,
        public bool $is_prepayment_available = false,
        public ?ProductDeliveryOptionPriceData $price_data = null,
        public array $fulfillment_type = [],
        public array $delivery_method = [],
        public ?string $status = null,
        public ?string $productable_type = null,
        public ?array $cover = [],

    ) {}

    public static function fromModel(
        ProductDeliveryOption $deliveryOption,
        ?ProductDeliveryOptionPriceData $priceData = null,
    ): self {
        $product = $deliveryOption->product;
        $media   = $product->productable->getAllMedia();
        $cover   = self::getCoverMedia($media);

        return new self(
            uuid: $deliveryOption->uuid,
            slug: $product->slug,
            name: $product->name,
            short_name: $product->short_name,
            short_description: $product->short_description,
            vendor: $product->vendor?->name,
            term: $product->term?->name,
            price: $deliveryOption->price,
            prepayment_amount: $deliveryOption->is_prepayment_available ? $deliveryOption->prepayment_amount : null,
            is_prepayment_available: $deliveryOption->is_prepayment_available,
            price_data: $priceData,
            fulfillment_type: [
                'value' => $deliveryOption->fulfillment_type->value,
                'label' => $deliveryOption->fulfillment_type->translate(),
            ],
            delivery_method: [
                'value' => $deliveryOption->delivery_method->value,
                'label' => $deliveryOption->delivery_method->translate(),
            ],
            status: $product->status?->value,
            productable_type: $product->productable_type,
            cover: $cover
        );
    }

    private static function getCoverMedia(array $media): ?array
    {
        if (isset($media[MediaTagEnum::COVER->value])) {
            $cover = $media[MediaTagEnum::COVER->value][0] ?? null;

            return $cover
                ? [
                    'url'       => $cover['url'],
                    'thumbnail' => $cover['thumbnail'],
                    'alt'       => $cover['alt'],
                ]
                : null;
        }

        return null;
    }
}
