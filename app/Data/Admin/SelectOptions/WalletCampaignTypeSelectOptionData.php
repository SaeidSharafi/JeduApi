<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use App\Enums\WalletCampaign\CampaignTypeEnum;
use Spatie\LaravelData\Data;

final class WalletCampaignTypeSelectOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public string $description,
    ) {}

    public static function fromCampaignType(CampaignTypeEnum $campaignType): self
    {
        return new self(
            value: $campaignType->value,
            label: $campaignType->translate(),
            description: $campaignType->getDescription(),
        );
    }
}
