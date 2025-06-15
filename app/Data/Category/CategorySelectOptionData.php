<?php

namespace App\Data\Category;

use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

class CategorySelectOptionData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        #[MapInputName('slug')]
        public string $subtitle,
        #[MapInputName('icon_url')]
        public string $image_url,
    )
    {
    }


}
