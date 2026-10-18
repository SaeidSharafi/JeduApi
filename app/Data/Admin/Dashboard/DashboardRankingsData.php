<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Product, category and vendor rankings of one dashboard period.
 *
 * Category revenue overlaps by design because a line belongs to every category of its
 * productable; it must never be summed into a period total.
 */
final class DashboardRankingsData extends Data
{
    /**
     * @param  list<DashboardRankingRowData>  $top_products
     * @param  list<DashboardRankingRowData>  $categories
     * @param  list<DashboardRankingRowData>  $vendors
     */
    public function __construct(
        public array $top_products,
        public array $categories,
        public array $vendors,
    ) {}
}
