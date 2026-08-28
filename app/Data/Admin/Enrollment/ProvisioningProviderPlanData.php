<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningReadinessEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class ProvisioningProviderPlanData extends Data
{
    public function __construct(
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ProvisioningProviderEnum $provider,
        public bool $applicable,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ProvisioningReadinessEnum $readiness,
        public ?string $configuration_issue,
    ) {}
}
