<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ProductDiscountHandler
{
    public function __construct(
        public string $key, // e.g. 'apply_percentage_off_product'
        public string $type = 'action' // or 'condition'
    ) {}
}
