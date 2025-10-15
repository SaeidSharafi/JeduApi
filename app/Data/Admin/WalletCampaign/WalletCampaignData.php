<?php

declare(strict_types=1);

namespace App\Data\Admin\WalletCampaign;

use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Admin\Staff\ShowStaffData;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

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
        public ?Verta $starts_at,
        public ?Verta $ends_at,
        public ?array $metadata,
        public ?Verta $created_at,
        public ?Verta $updated_at,
        // Computed fields
        public ?int $remaining_usage_count,
        public bool $is_within_date_range,
        #[MapOutputName('created_by')]
        public ?ShowStaffData $auditor = null,
        public ?int $transactions_count = null,
    ) {}
}
