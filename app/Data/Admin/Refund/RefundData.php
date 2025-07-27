<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use App\Actions\Admin\Refund\Max;
use App\Actions\Admin\Refund\Rule;
use App\Actions\Admin\Refund\ValidationContext;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\RefundStatusEnum;
use App\Rules\IbanNumberRule;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class RefundData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $deduction_amount,
        public readonly RefundTransactionData $transaction_details,
        #[WithCast(EnumCast::class),WithTransformer(TranslatableEnumData::class)]
        public readonly RefundStatusEnum $status,
        public readonly ?string $admin_notes,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $updated_at,
    ) {}

}
