<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/** Daily revenue/orders points for the period and for the period before it. */
final class DashboardSummaryTrendData extends Data
{
    public function __construct(
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $start,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $end,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $previous_start,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $previous_end,
        /** @var DataCollection<int, DashboardSummaryTrendPointData> */
        #[DataCollectionOf(DashboardSummaryTrendPointData::class)]
        public DataCollection $points,
        /** @var DataCollection<int, DashboardSummaryTrendPointData> */
        #[DataCollectionOf(DashboardSummaryTrendPointData::class)]
        public DataCollection $previous_points,
    ) {}
}
