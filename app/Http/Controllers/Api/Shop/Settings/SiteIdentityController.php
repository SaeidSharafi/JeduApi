<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\SiteIdentityData as AdminSiteIdentityData;
use App\Data\Shop\Site\SiteIdentityData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/**
 * @group Shop - Settings
 *
 * API for retrieving site configuration
 */
final class SiteIdentityController extends Controller
{
    /**
     * Get Site Identity
     *
     * Returns the title and favicon used for page metadata (tab title, Open Graph
     * name). The admin panel reads the same payload before a staff session exists.
     *
     * @responseFile 200 resources/responses/shop/settings/site-identity.json
     */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $identity = AdminSiteIdentityData::from(
            $service->get(SettingKeyEnum::SITE_IDENTITY, AdminSiteIdentityData::getDefaults())
        );

        return apiResponse()->success(new SiteIdentityData(
            site_title: $identity->site_title,
            favicon_url: $identity->favicon_url,
        ));
    }
}
