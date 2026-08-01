<?php

declare(strict_types=1);

namespace App\Enums\Product;

final class ProductSortFieldEnum
{
    public const array ALLOWED = [
        'created_at',
        'updated_at',
        'name',
        'short_name',
        'price',
        'capacity_utilization',
    ];
}
