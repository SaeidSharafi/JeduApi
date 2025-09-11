<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

final class ArticleSectionData extends Data
{
    public function __construct(
        public string $title,
        public readonly string $content,
        public ?MediaData $icon = null,
        public readonly ?string $subtitle = null,
    ) {}
}
