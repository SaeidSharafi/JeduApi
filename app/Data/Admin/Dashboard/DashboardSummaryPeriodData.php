<?php

declare(strict_types=1);

namespace App\Data\Admin\Dashboard;

use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

/** The reporting window a dashboard block was computed for, current and previous. */
final class DashboardSummaryPeriodData extends Data
{
    public function __construct(
        public string $key,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $start,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $end,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $previous_start,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $previous_end,
    ) {}
}
