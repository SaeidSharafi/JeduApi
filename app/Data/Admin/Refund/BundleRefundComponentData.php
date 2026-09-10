<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrollmentRevocationStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * One component's financial and revocation outcome inside a Bundle refund
 * response. Keeps the policy share, the effective (capped/redistributed)
 * deduction, and the actual refund separately for auditability.
 */
final class BundleRefundComponentData extends Data
{
    public function __construct(
        public int $order_item_id,
        public int $product_delivery_option_id,
        public string $name,
        public string $sku,
        public int $base_price,
        public int $paid_amount,
        public int $policy_deduction_amount,
        public int $effective_deduction_amount,
        public int $redistributed_amount,
        public int $refund_amount,
        public ?int $refund_id,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?EnrollmentRevocationStatusEnum $revocation_status = null,
    ) {}
}
