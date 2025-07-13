<?php

namespace App\Data\Shop\Product;

use App\Enums\ProductableMediaTypeEnum;
use Spatie\LaravelData\Data;

class ProductCardData extends Data
{
    public function __construct(
        public int $id,
        public int $productable_id,
        public ?string $name = null,
        public ?string $short_name = null,
        public ?string $short_description = null,
        public array $vendor,
        public array $term,
        public array $prices = [],
        public int $lowest_price = 0,
        public int $highest_price = 0,
        public array $fullfilment_options = [],
        public ?string $status = null,
        public ?bool $is_visible = null,
        public ?bool $is_featured = null,
        public ?string $productable_type = null,
        public ?array $cover = [],

    ) {
    }

    public static function fromModel(\App\Models\Product $product): self
    {
        $pricesCollection = $product->productDeliveryOptions->pluck('price');
        $lowest_price = $pricesCollection->min();
        $highest_price = $pricesCollection->max();
        $prices = $product->productDeliveryOptions->map(fn($option) => [
            'id' => $option->id,
            'price' => $option->price
        ])->all();

        $fullfilment_options = $product->productDeliveryOptions->map(fn($deliveryOption) => [
            'value' => $deliveryOption->fulfillment_type->value,
            'label' => $deliveryOption->fulfillment_type->translate(),
        ])->unique('value')->values()->all();

        $media = $product->productable->getProductableMedia();
        $cover = self::getCoverMedia($media);


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
            prices: $prices,
            lowest_price: $lowest_price ?? 0,
            highest_price: $highest_price ?? 0,
            fullfilment_options: $fullfilment_options,
            status: $product->status?->value,
            is_visible: $product->is_visible,
            is_featured: $product->is_featured,
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
