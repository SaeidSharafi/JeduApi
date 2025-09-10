<?php

namespace App\Data;

use App\Data\Admin\MediaData;
use Illuminate\Support\Optional;
use Spatie\LaravelData\Data;

class ArticleSectionData extends Data
{
    public function __construct(
        public string $title,
        public readonly string $content,
        public ?MediaData $icon = null,
        public readonly ?string $subtitle = null,
    )
    {
    }
}
