<?php

declare(strict_types=1);

namespace App\Data\Shop\CMS;

use Spatie\LaravelData\Data;

final class ShopArticleSectionData extends Data
{
    public function __construct(
        public string $title,
        public readonly string $content,
        public ?string $icon_url,
        public readonly ?string $subtitle = null,
    ) {}
}
