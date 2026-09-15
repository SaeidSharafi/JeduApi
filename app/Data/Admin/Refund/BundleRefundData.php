<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\BundlePurchaseStatusEnum;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * Response for one indivisible Bundle Purchase refund: the Bundle-level
 * financial summary plus every component's financial and revocation
 * calculation.
 */
final class BundleRefundData extends Data
{
    public function __construct(
        public int $bundle_purchase_id,
        public string $bundle_name,
        public string $sku,
        public int $base_value,
        public int $paid_amount,
        public int $policy_deduction_amount,
        public int $effective_deduction_amount,
        public int $refund_amount,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public BundlePurchaseStatusEnum $status,
        #[DataCollectionOf(BundleRefundComponentData::class)]
        public Collection $components,
    ) {}
}
