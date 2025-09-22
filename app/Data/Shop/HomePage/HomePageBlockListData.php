<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use Spatie\LaravelData\Data;

final class HomePageBlockListData extends Data
{
    public function __construct(
        public int $id,
        public string $location,
        public int $order = 0,
        public ?string $preset = null,
    ) {}
}
