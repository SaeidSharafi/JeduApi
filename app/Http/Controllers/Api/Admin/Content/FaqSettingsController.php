<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\FaqData;
use App\Data\Admin\Settings\FaqUpdateData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Settings Management
 *
 * @authenticated
 */
final class FaqSettingsController extends Controller
{
    /**
     * Get FAQ settings.
     *
     * @responseFile resources/responses/admin/faq/show.json
     */
    public function show(SettingsService $settings): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return apiResponse()->success(FaqData::from($settings->get(SettingKeyEnum::FAQ, FaqData::getDefaults())));
    }

    /**
     * Replace FAQ settings.
     *
     * @responseFile resources/responses/admin/faq/show.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(FaqUpdateData $data, SettingsService $settings): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);
        $settings->set(SettingKeyEnum::FAQ, $data->toArray(), 'json', 'cms');

        return apiResponse()->success(FaqData::from($settings->get(SettingKeyEnum::FAQ, FaqData::getDefaults())));
    }
}
