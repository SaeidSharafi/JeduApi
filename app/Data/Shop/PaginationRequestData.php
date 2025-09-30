<?php

namespace App\Data\Shop;

use Spatie\LaravelData\Data;

class PaginationRequestData extends Data
{
    public function __construct(
        public ?int $page = null,
        public ?int $per_page = 15,
    )
    {
    }
}
