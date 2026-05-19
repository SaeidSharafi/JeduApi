<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses\Blocks;

use Spatie\LaravelData\Data;

final class LiveSessionSkyroomBlockData extends Data
{
    public function __construct(
        public ?string $join_url,
        public ?string $start_date,
        public array $past_recordings,
    ) {}
}
