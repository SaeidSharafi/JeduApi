<?php

declare(strict_types=1);

namespace App\Data\Admin\Wallet;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Admin\Staff\ShowStaffData;
use App\Enums\Wallet\CampaignTypeEnum;
use App\Models\Staff;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class WalletCampaignData extends Data implements WalletTransactionSourceableDataContract
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public CampaignTypeEnum $type,
        public bool $is_active,
        public int $amount,
        public ?int $usage_limit_total,
        public ?int $usage_limit_per_user,
        public int $total_usage_count,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $starts_at,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $ends_at,
        public ?array $metadata,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $updated_at,
        // Computed fields
        public ?int $remaining_usage_count,
        public bool $is_within_date_range,
        #[MapOutputName('created_by')]
        public ?ShowStaffData $auditor= null,
        public ?int $transactions_count = null,
    ) {
    }
}
