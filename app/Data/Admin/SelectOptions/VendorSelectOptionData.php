<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class VendorSelectOptionData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        #[MapInputName('address')]
        public string $subtitle,
        #[MapInputName('logo_url')]
        public ?string $image_url = null,
    ) {}
}
