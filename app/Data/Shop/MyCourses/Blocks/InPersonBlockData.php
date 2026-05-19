<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses\Blocks;

use Spatie\LaravelData\Data;

final class InPersonBlockData extends Data
{
    public function __construct(
        public ?string $address,
        public ?string $map_url,
    ) {}
}
