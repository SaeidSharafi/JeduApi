<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\CMS;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\ContactInfoData;
use App\Data\Shop\CMS\ContactPageData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/**
 * @group Shop - CMS
 *
 * API for retrieving site configuration
 */
final class ContactPageController extends Controller
{
    /**
     * Get Contact Page Configuration
     *
     * Returns the configuration settings for the Contact page.
     *
     * @responseFile storage/responses/shop/contactpage/show.json
     */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $contactPage = $service->get(SettingKeyEnum::CONTACT_INFO, ContactInfoData::getDefaults());

        return response()->success(ContactPageData::fromSetting($contactPage));
    }
}
