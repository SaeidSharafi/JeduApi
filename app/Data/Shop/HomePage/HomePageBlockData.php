<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use Spatie\LaravelData\Data;

final class HomePageBlockData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public string $title,
        public string $location,
        public array $content,
    ) {}
}
