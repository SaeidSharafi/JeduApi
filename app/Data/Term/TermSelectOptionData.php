<?php

declare(strict_types=1);

namespace App\Data\Term;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class TermSelectOptionData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        #[MapInputName('academic_year')]
        public string $subtitle,
        public ?string $image_url = null,
    ) {}
}
