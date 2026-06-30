<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Site\HeaderData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/**
 * @group Shop - Settings
 *
 * API for retrieving site configuration
 */
final class HeaderController extends Controller
{
    /**
     * Get Header Configuration
     *
     * Returns the configuration settings for the website header.
     *
     * @responseFile 200 resources/responses/shop/settings/header.json
     * */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $header = $service->get(SettingKeyEnum::HEADER, \App\Data\Admin\Settings\HeaderData::getDefaults());

        return apiResponse()->success(HeaderData::from($header));
    }
}
