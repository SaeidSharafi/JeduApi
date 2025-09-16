<?php

declare(strict_types=1);

namespace App\Data\Admin\Staff;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class StaffSelectOptionData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        #[MapInputName('email')]
        public string $subtitle,
        public ?string $image_url = null,
    ) {}
}
