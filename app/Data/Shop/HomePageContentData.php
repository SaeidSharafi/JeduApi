<?php

declare(strict_types=1);

namespace App\Data\Shop;

use Spatie\LaravelData\Data;

final class HomePageContentData extends Data
{
    public function __construct(
        public array $hero,
        public array $main_content,
    ) {}
}
