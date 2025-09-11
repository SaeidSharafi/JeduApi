<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class ArticleSectionCreateData extends Data
{
    public function __construct(
        public string $title,
        public readonly string $content,
        public ?int $icon = null,
        public readonly ?string $subtitle = null,
    ) {}
}
