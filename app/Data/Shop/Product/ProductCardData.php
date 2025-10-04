<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Data\Shop\ProductPriceData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class ProductCardData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?int $price,
        public ?int $original_price,
        public ?array $price_range,
        public ?bool $has_discount,
        public ?float $discount_percent,
        public bool $is_free,
        public bool $is_featured,
        #[WithTransformer(TranslatableEnumData::class)]
        public ProductableEnum $product_type,
        public ?string $thumbnail_url,
        public ?array $teachers,
        public ?int $reviews_count,
        public ?float $average_rating,
        public ?ProductPriceData $price_data = null,
    ) {}

    public static function fromModel(Product $product, ProductPriceData $priceData, bool $withFullPriceData = true): self
    {
        $teachers = $product->productDeliveryOptions->flatMap(fn ($option) => $option->getTeachersName())->unique()->values()->toArray();
        if (! $teachers) {
            $teachers = isset($product->productable?->default_teacher_info) ? [$product->productable?->default_teacher_info] : [];
        }

        return new self(
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
            thumbnail_url: $product->productable->thumbnail_url ?? null,
            teachers: $teachers,
            reviews_count: $product->reviews_count   ?? 0,
            average_rating: $product->average_rating ?? 0.0,
            price_data: $withFullPriceData ? $priceData : null,
        );
    }
}
