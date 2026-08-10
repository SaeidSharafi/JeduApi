<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use Spatie\LaravelData\Data;

final class DeliveryOptionSelectOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
