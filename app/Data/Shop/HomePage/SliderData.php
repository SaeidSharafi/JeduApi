<?php

namespace App\Data\Shop\HomePage;

use Spatie\LaravelData\Data;

class SliderData extends Data
{
    public function __construct(
        public ?string $title,
        public ?string $caption,
        public ?string $image_url,
        public ?string $image_alt,
        public ?string $link,
        public ?int $order,
    )
    {
    }
}
