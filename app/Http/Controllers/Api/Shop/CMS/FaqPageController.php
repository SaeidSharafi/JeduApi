<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\CMS;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\FaqData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/** @group Shop - CMS */
final class FaqPageController extends Controller
{
    /**
     * Get FAQ page content.
     *
     * @responseFile resources/responses/shop/faq/show.json
     */
    public function __invoke(SettingsService $settings): ApiResponseInterface
    {
        return apiResponse()->success(FaqData::from($settings->get(SettingKeyEnum::FAQ, FaqData::getDefaults())));
    }
}
