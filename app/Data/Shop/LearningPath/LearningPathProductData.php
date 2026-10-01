<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Data\Shop\ProductPriceData;
use App\Services\LearningPath\ResolvedLearningPathStep;
use Spatie\LaravelData\Data;

/**
 * Presents a learning-path step as one storefront product projection.
 *
 * A matching Product overrides the productable's name, short description, and
 * slug. The productable supplies the fallback identity and thumbnail, while
 * price fields remain null when no sellable Product is available.
 */
final class LearningPathProductData extends Data
{
    public function __construct(
        public ?string $slug,
        public string $name,
        public string $productable_type,
        public ?string $short_description,
        public ?string $excerpt,
        public ?string $thumbnail_url,
        public ?int $price,
        public ?ProductPriceData $price_data,
    ) {}

    public static function fromResolved(ResolvedLearningPathStep $resolved, string $productableType): self
    {
        $product     = $resolved->product;
        $productable = $resolved->step->productable;

        $name    = $product?->name ?: $productable?->full_name;
        $excerpt = $product?->short_description ?: $productable?->description;
        $slug    = $product?->slug ?: $productable?->slug;

        return new self(
            slug: $slug,
            name: (string) $name,
            productable_type: $productableType,
            short_description: $excerpt,
            excerpt: $excerpt,
            thumbnail_url: $productable?->thumbnail_url,
            price: $resolved->priceData?->min_price,
            price_data: $resolved->priceData,
        );
    }
}
