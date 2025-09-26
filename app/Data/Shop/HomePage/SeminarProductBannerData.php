<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use App\Data\Shop\ProductPriceData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class SeminarProductBannerData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?int $price,
        public ?int $original_price,
        public ?array $price_range,
        public ?bool $has_discount,
        public ?flointat $discount_percent,
        public bool $is_free,
        public bool $is_featured,
        #[WithTransformer(TranslatableEnumData::class)]
        public ProductableEnum $product_type,
        public ?string $thumbnail_url,
        public ?string $teacher_name,
        public ?int $reviews_count,
        public ?float $average_rating,
        public ?string $start_date,
        public ?string $end_date,
        public ?string $location,
        public ?string $registration_deadline,
        public ProductPriceData $price_data,

    ) {}

    public static function fromModel(Product $product, ProductPriceData $priceData): self
    {
        return new self(
            id: $product->id,
            slug: $product->slug,
            name: $product->name,
            price: $priceData->min_price,
            original_price: $priceData->min_original_price,
            price_range: $priceData->range,
            has_discount: $priceData->has_discount,
            discount_percent: $priceData->discount_percentage,
            is_free: ($priceData?->min_price ?? 0) <= 0,
            is_featured: $product->is_featured,
            product_type: ProductableEnum::from($product->productable_type),
            thumbnail_url: $product->productable->thumbnail_url       ?? null,
            teacher_name: $entity->productable?->default_teacher_info ?? '',
            reviews_count: $product->reviews_count                    ?? 0,
            average_rating: $product->average_rating                  ?? 0.0,
            start_date: data_get($product, 'details_json.start_date') ? verta(data_get($product,
                'details_json.start_date'))->format('Y-m-d H:i:s') : null,
            end_date: data_get($product, 'details_json.end_date') ? verta(data_get($product,
                'details_json.end_date'))->format('Y-m-d H:i:s') : null,
            location: data_get($product, 'details_json.location'),
            registration_deadline: data_get($product, 'details_json.registration_deadline'),
            price_data: $priceData,
        );
    }
}
