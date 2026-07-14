<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class BlogCategorySelectOptionData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        #[MapInputName('slug')]
        public string $subtitle,
        #[MapInputName('icon')]
        public string $icon_url,
    ) {}
}
