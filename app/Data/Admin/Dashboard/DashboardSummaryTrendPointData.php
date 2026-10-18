<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

/** One day of the revenue/orders trend, dated in Jalali. */
final class DashboardSummaryTrendPointData extends Data
{
    public function __construct(
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $date,
        public int $revenue,
        public int $orders,
    ) {}
}
