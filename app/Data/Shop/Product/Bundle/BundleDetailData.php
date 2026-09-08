<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Bundle;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Product;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class BundleDetailData extends Data
{
    public function __construct(
        public string $slug,
        public string $full_name,
        public string $short_name,
        public ?string $description,
        public ?array $faq,
        public ?array $additional_info,
        public ?array $properties,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public PublicationStatusEnum $status,
        public string $productable_type,
        public int $product_id,
        public string $product_name,
        public Collection $options,
        public array $media = [],
    ) {}

    /** @param Collection<int, BundleOptionData> $options */
    public static function fromModel(Product $product, Collection $options): self
    {
        $bundle = $product->productable;

        return new self(
            slug: $product->slug,
            full_name: (string) ($product->name ?? $bundle->full_name),
            short_name: $product->short_name,
            description: $bundle->description,
            faq: $bundle->faq,
            additional_info: $bundle->additional_info,
            properties: $bundle->properties,
            meta_title: $bundle->meta_title,
            meta_description: $bundle->meta_description,
            meta_keywords: $bundle->meta_keywords,
            status: $product->status,
            productable_type: $product->productable_type,
            product_id: $product->id,
            product_name: $product->name,
            options: $options,
            media: $bundle->getAllMedia(urlOnly: true),
        );
    }
}
