<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\FooterData as AdminFooterData;
use App\Data\Shop\Site\FooterData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\SettingsService;

/**
 * @group Shop - Settings
 *
 * API for retrieving site configuration
 */
final class FooterController extends Controller
{
    /**
     * Get Footer Configuration
     *
     * Returns the configuration settings for the website footer.
     *
     * @responseFile 200 resources/responses/shop/settings/footer.json
     */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $footer = $service->get(SettingKeyEnum::FOOTER, AdminFooterData::getDefaults());
        $footer['categories'] = Category::query()
            ->whereIn('id', data_get($footer, 'categories', []))
            ->get(['name', 'slug'])->toArray();
        return apiResponse()->success(FooterData::from($footer));
    }
}
