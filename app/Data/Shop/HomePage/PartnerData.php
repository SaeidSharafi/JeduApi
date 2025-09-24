<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use Spatie\LaravelData\Data;

final class PartnerData extends Data
{
    public function __construct(
        public string $title,
        public ?string $caption,
        public ?string $image_url,
        public ?string $url,
        public int $order,
    ) {}
}
