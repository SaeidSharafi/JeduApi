<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\ProvisioningStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class ProvisioningPlanData extends Data
{
    public function __construct(
        public int $version,
        #[DataCollectionOf(ProvisioningProviderPlanData::class)]
        public DataCollection $providers,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ProvisioningStatusEnum $status,
        public ?Verta $resolved_at,
    ) {}
}
