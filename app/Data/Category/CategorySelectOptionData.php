<?php

declare(strict_types=1);

namespace App\Data\Category;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class CategorySelectOptionData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        #[MapInputName('slug')]
        public string $subtitle,
        #[MapInputName('icon_url')]
        public string $image_url,
    ) {}
}
