<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\CMS;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\CollaborationPageData as AdminCollaborationPageData;
use App\Data\Shop\CMS\CollaborationPageData;
use App\Enums\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/**
 * @group Shop - CMS
 *
 * API for retrieving site configuration
 */
final class CollaborationPageController extends Controller
{
    /**
     * Get Collaboration Page Configuration
     *
     * Returns the configuration settings for the Contact page.
     *
     * @responseFile storage/responses/shop/collaboration/show.json
     */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $contactPage = $service->get(SettingKeyEnum::COLLABORATION, AdminCollaborationPageData::getDefaults());
        return response()->success(CollaborationPageData::fromSetting($contactPage));
    }
}
