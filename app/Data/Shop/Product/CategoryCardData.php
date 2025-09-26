<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use Spatie\LaravelData\Data;

final class CategoryCardData extends Data
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $icon_url = null,
        public ?string $image_url = null,
    ) {}
}
