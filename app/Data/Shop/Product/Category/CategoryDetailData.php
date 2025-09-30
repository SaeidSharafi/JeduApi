<?php

namespace App\Data\Shop\Product\Category;

use App\Data\Shop\Product\ProductCardData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CategoryDetailData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description = null,
        public ?string $educational_calendar_url = null,
        public ?string $color_scheme = null,
        public ?string $icon_url = null,
        public ?string $image_url = null,
        public ?string $meta_title = null,
        public ?string $meta_description = null,
        public ?string $meta_keywords = null,
        #[DataCollectionOf(ProductCardData::class)]
        public ?Collection $courses = null,
        #[DataCollectionOf(ProductCardData::class)]
        public ?Collection $seminars = null,
        #[DataCollectionOf(ProductCardData::class)]
        public ?Collection $digital_assets = null,
    ) {
    }
}
