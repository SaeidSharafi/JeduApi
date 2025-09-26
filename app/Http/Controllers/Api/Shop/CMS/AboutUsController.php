<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\CMS;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\AboutUsData as AdminAboutUsData;
use App\Data\Shop\CMS\AboutUsData;
use App\Enums\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/**
 * @group Shop - CMS
 *
 * API for retrieving site configuration
 */
final class AboutUsController extends Controller
{
    /**
     * Get About Us Configuration
     *
     * Returns the configuration settings for the About Us page.
     *
     * @responseFile storage/responses/shop/aboutus/show.json
     */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $aboutUs = $service->get(SettingKeyEnum::ABOUT_US, AdminAboutUsData::getDefaults());
        return response()->success(AboutUsData::fromSetting($aboutUs));
    }
}
