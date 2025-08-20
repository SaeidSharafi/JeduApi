<?php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class DiscountHandlerKey
{
    public function __construct(
        public string $key,
    ) {
    }
}
