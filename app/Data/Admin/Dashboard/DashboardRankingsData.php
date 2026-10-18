<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Product, category and vendor rankings of one dashboard period.
 *
 * Shares are of the cohort's included line revenue, not of the returned rows: category shares
 * may sum above 100 % because a line belongs to every category of its productable, while a cap
 * and unattributed lines leave the remainder visible. Category revenue must never be summed
 * into a period total.
 */
final class DashboardRankingsData extends Data
{
    public function __construct(
        /** @var DataCollection<int, DashboardRankingRowData> */
        #[DataCollectionOf(DashboardRankingRowData::class)]
        public DataCollection $top_products,
        /** @var DataCollection<int, DashboardRankingRowData> */
        #[DataCollectionOf(DashboardRankingRowData::class)]
        public DataCollection $categories,
        /** @var DataCollection<int, DashboardRankingRowData> */
        #[DataCollectionOf(DashboardRankingRowData::class)]
        public DataCollection $vendors,
    ) {}
}
