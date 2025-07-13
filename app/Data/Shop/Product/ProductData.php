<?php

namespace App\Data\Shop\Product;

use App\Enums\ProductableMediaTypeEnum;
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public int $id,
        public int $productable_id,
        public ?string $name = null,
        public ?string $short_name = null,
        public ?string $short_description = null,
        public array $vendor,
        public array $term,
        public array $outcomes = [],
        public array $teachers = [],
        public array $prices = [],
        public int $lowest_price = 0,
        public int $highest_price = 0,
        public array $fullfilment_options = [],
        public ?string $status = null,
        public ?bool $is_visible = null,
        public ?bool $is_featured = null,
        public ?string $productable_type = null,
        public array $details_json = [],
        public array $media = [],
        public string $meta_title = '',
        public string $meta_description = '',
        public string $meta_keywords = '',
        public array $additional_info = [],

    ) {
    }

    public static function fromModel(\App\Models\Product $product): self
    {
        $teachers = $product->productDeliveryOptions->flatMap(function ($deliveryOption) {
            return $deliveryOption->teachers->map(fn($teacher) => [
                'id'     => $teacher->id,
                'name'   => $teacher->first_name . ' ' . $teacher->last_name,
                'bio'    => $teacher->bio,
                'avatar' => $teacher->getMedia('profile')?->first()?->getUrl(),
            ]);
        })->unique('id')->values()->all();

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

        $media = [
            'gallery'     => self::getGalleryMedia($media),
            'video'       => self::getVideoMedia($media),
            'cover'       => self::getCoverMedia($media),
            'certificate' => self::getCertificateMedia($media),
        ];

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
            outcomes: $product->productable->outcomes_json ?? [],
            teachers: $teachers,
            prices: $prices,
            lowest_price: $lowest_price ?? 0,
            highest_price: $highest_price ?? 0,
            fullfilment_options: $fullfilment_options,
            status: $product->status?->value,
            is_visible: $product->is_visible,
            is_featured: $product->is_featured,
            productable_type: $product->productable_type,
            details_json: $product->details_json,
            media: $media,
            meta_title: $product->productable->meta_title ?? '',
            meta_description: $product->productable->meta_description ?? '',
            meta_keywords: $product->productable->meta_keywords ?? '',
            additional_info: $product->productable->additional_info ?? [],
        );
    }

    private static function getGalleryMedia(array $media): array
    {
        if (isset($media[ProductableMediaTypeEnum::GALLERY->value])) {
             return array_map(
                fn($item) => [
                    'url'       => $item['url'],
                    'thumbnail' => $item['thumbnail'],
                    'alt'       => $item['alt'],
                ],
                $media[ProductableMediaTypeEnum::GALLERY->value]
            );
        }
        return [];
    }

    private static function getVideoMedia(array $media): array
    {
        if (isset($media[ProductableMediaTypeEnum::VIDEO->value])) {
            return array_map(
                fn($item) => [
                    'url'       => $item['url'],
                    'thumbnail' => $item['thumbnail'],
                    'alt'       => $item['alt'],
                ],
                $media[ProductableMediaTypeEnum::VIDEO->value]
            );
        }
        return [];
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

    private static function getCertificateMedia(array $media): ?array
    {
        if (isset($media[ProductableMediaTypeEnum::CERTIFICATE->value])) {
            $certificate = $media[ProductableMediaTypeEnum::CERTIFICATE->value][0] ?? null;
            return $certificate
                ? [
                    'url'       => $certificate['url'],
                    'thumbnail' => $certificate['thumbnail'],
                    'alt'       => $certificate['alt'],
                ]
                : null;
        }
        return null;
    }
}
