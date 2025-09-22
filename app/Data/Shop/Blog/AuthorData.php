<?php

declare(strict_types=1);

namespace App\Data\Shop\Blog;

use Spatie\LaravelData\Data;

final class AuthorData extends Data
{
    public function __construct(
        public string $name,
    ) {}
}
