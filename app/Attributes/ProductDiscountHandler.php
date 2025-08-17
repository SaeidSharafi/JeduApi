<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ProductDiscountHandler
{
    public function __construct(
        public string $key, // e.g. 'apply_percentage_off_product'
        public string $type = 'action' // or 'condition'
    ) {}
}
