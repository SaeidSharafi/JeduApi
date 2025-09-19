<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Enums\ProductableMediaTypeEnum;
use App\Models\ProductDeliveryOption;
use Spatie\LaravelData\Data;

final class ProductDeliveryOptionCardData extends Data
{
    public function __construct(
        public int $id,
        public int $productable_id,
        public ?string $name,
        public ?string $short_name,
        public ?string $short_description,
        public array $vendor,
        public array $term,
        public int $price,
        public array $fullfilment_type = [],
        public array $delivery_method = [],
        public ?string $status = null,
        public ?string $productable_type = null,
        public ?array $cover = [],

    ) {}

    public static function fromModel(ProductDeliveryOption $deliveryOption): self
    {
        $product = $deliveryOption->product;
        $media   = $product->productable->getAllMedia();
        $cover   = self::getCoverMedia($media);

        return new self(
            id: $product->id,
            productable_id: $product->productable_id,
            name: $product->name,
            short_name: $product->short_name,
            short_description: $product->short_description,
            vendor: [
                'id'   => $product->vendor?->id,
                'name' => $product->vendor?->name,
            ],
            term: [
                'id'   => $product->term?->id,
                'name' => $product->term?->name,
            ],
            price: $deliveryOption->price,
            fullfilment_type: [
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
        if (isset($media[ProductableMediaTypeEnum::COVER->value])) {
            $cover = $media[ProductableMediaTypeEnum::COVER->value][0] ?? null;

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
