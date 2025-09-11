<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class DiscountHandlerKey
{
    public function __construct(
        public string $key,
    ) {}
}
