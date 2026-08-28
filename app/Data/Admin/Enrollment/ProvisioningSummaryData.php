<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\ProvisioningStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class ProvisioningSummaryData extends Data
{
    public function __construct(
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ProvisioningStatusEnum $status,
        public ProvisioningPlanData $plan,
        public ?string $reconciliation_status = null,
    ) {}
}
