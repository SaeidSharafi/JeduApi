<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses;

use Spatie\LaravelData\Data;

final class JoinUrlData extends Data
{
    public function __construct(
        public string $url,
        public string $type,
        public ?string $expires_at = null,
    ) {}
}
