<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Data\Shop\ProductPriceData;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class LearningPathCurrentProductData extends Data
{
    /**
     * @param  array<int, LearningPathMediaData>  $media
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $productable_type,
        public ?string $short_description,
        public ?string $excerpt,
        public ?string $thumbnail_url,
        public ?int $price,
        public ?ProductPriceData $price_data,
        public array $media = [],
    ) {}

    public static function fromModels(
        Product $product,
        ?Model $fallback,
        ?ProductPriceData $priceData,
        string $productableType,
    ): self {
        $fallbackName    = $fallback?->getAttribute('full_name') ?: $fallback?->getAttribute('name');
        $fallbackExcerpt = $fallback?->getAttribute('description');
        $name            = $product->name ?: $fallbackName;
        $excerpt         = $product->short_description ?: $fallbackExcerpt;

        return self::factory()->withoutMagicalCreation()->from([
            'id'                => (int) $product->getKey(),
            'slug'              => $product->slug,
            'name'              => (string) $name,
            'productable_type'  => (string) ($product->productable_type ?: $productableType),
            'short_description' => $excerpt,
            'excerpt'           => $excerpt,
            'thumbnail_url'     => $fallback?->getAttribute('thumbnail_url'),
            'price'             => $priceData?->min_price,
            'price_data'        => $priceData,
            'media'             => $fallback ? LearningPathMediaData::forModel($fallback) : [],
        ]);
    }
}
