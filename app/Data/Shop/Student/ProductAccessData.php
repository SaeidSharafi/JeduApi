<?php

declare(strict_types=1);

namespace App\Data\Shop\Student;

use Spatie\LaravelData\Data;

final class ProductAccessData extends Data
{
    public function __construct(
        public string $option_uuid,
        public string $enrollment_uuid,
        public string $status,
        public string $product_type,
        public ?string $asset_uuid,
    ) {}
}
