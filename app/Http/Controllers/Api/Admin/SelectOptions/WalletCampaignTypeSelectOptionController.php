<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\SelectOptions\WalletCampaignTypeSelectOptionData;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Http\Controllers\Controller;
use Spatie\LaravelData\DataCollection;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 */
final class WalletCampaignTypeSelectOptionController extends Controller
{
    /**
     * Wallet campaign types list
     *
     * Retrieve the available wallet campaign types for select inputs.
     *
     * @responseFile 200 resources/responses/admin/select-options/wallet-campaign-types.json
     */
    public function __invoke(): ApiResponseInterface
    {
        $options = array_map(
            static fn (CampaignTypeEnum $type): WalletCampaignTypeSelectOptionData => WalletCampaignTypeSelectOptionData::fromCampaignType($type),
            CampaignTypeEnum::cases(),
        );

        return apiResponse()->success(
            new DataCollection(WalletCampaignTypeSelectOptionData::class, $options)
        );
    }
}
