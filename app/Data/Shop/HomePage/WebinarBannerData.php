<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use App\Data\Shop\ProductPriceData;
use App\Models\Product;
use Spatie\LaravelData\Data;

final class WebinarBannerData extends Data
{
    public function __construct(
        public ?string $image_url = null,
        public ?string $text = null,
        public ?SeminarProductBannerData $product = null,
    ) {}

    public static function fromBlock(array $blockContent, ?Product $product, ?ProductPriceData $priceData): self
    {
        return new self(
            image_url: $blockContent['image_url'] ?? null,
            text: $blockContent['text']           ?? null,
            product: $product ? SeminarProductBannerData::fromModel($product, $priceData) : null
        );

    }
}
