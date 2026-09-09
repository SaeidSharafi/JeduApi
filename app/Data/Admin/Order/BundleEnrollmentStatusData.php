<?php

declare(strict_types=1);

namespace App\Data\Admin\Order;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class BundleEnrollmentStatusData extends Data
{
    public function __construct(
        public int $id,
        public string $uuid,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public EnrollmentStatusEnum $enrollment_status,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ProvisioningStatusEnum $provisioning_status,
    ) {}
}
