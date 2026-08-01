<?php

declare(strict_types=1);

namespace App\Query;

use App\Enums\Product\ProductSortFieldEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ProductListing
{
    public static function forListing(Builder $query): Builder
    {
        return $query->forListing();
    }

    public static function forDetail(Builder $query): Builder
    {
        return $query->forDetail();
    }

    public static function sortBy(Builder $query, string $field, string $direction = 'desc'): Builder
    {
        if (! in_array($field, ProductSortFieldEnum::ALLOWED, true) || ! in_array($direction, ['asc', 'desc'], true)) {
            return $query;
        }

        if ($field === 'capacity_utilization') {
            return self::sortByCapacityUtilization($query);
        }

        if ($field === 'price') {
            return $query->join('product_prices', 'products.id', '=', 'product_prices.product_id')
                ->select('products.*')
                ->orderBy('product_prices.min_price', $direction);
        }

        return $query->orderBy("products.{$field}", $direction);
    }

    public static function sortByCapacityUtilization(Builder $query, float $threshold = 0.8): Builder
    {
        return $query->sortByCapacityUtilization($threshold);
    }

    public static function popular(Builder $query): Builder
    {
        return $query->withCount('orderItems')->orderByDesc('order_items_count');
    }

    public static function paginate(Builder $query, int $perPage = 15): LengthAwarePaginator
    {
        return $query->paginate($perPage)->withQueryString();
    }
}
