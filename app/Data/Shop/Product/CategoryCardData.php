<?php

namespace App\Data\Shop\Product;

use Spatie\LaravelData\Data;

class CategoryCardData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $slug = null,
        public ?string $icon_url = null,
        public ?string $image_url = null,
    )
    {
    }
}
