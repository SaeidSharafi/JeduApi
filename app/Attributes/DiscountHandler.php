<?php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class DiscountHandler
{
    public function __construct(
        public string $key, // e.g. 'apply_percentage_off_product'
        public string $type = 'action' // or 'condition'
    ) {}
}
