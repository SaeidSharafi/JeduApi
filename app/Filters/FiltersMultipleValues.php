<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FiltersMultipleValues implements Filter
{

    /**
     * @inheritDoc
     */
    public function __invoke(Builder $query, mixed $value, string $property)
    {
        // If the value is a string, explode it into an array.
        // If it's already an array, it will be used as is.
        $values = is_array($value) ? $value : explode(',', $value);

        $query->whereIn($property, $values);
    }
}
