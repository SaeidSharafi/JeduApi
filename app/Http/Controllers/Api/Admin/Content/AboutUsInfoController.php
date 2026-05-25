<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\AboutUsCreateData;
use App\Data\Admin\Settings\AboutUsData;
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
final class AboutUsInfoController extends Controller
{
    /**
     * Get about us settings.
     *
     * @responseFile 200 responses/settings/about-us.json
     */
    public function show(SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);
        $aboutUs = $settingsService->get(SettingKeyEnum::ABOUT_US, AboutUsData::getDefaults());

        return response()->success(AboutUsData::from($aboutUs));
    }

    /**
     * Update about us settings.
     *
     * @responseFile 200 responses/settings/about-us.json
     * @responseFile 422 responses/422.json
     */
    public function update(AboutUsCreateData $data, SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        $settingsService->set(SettingKeyEnum::ABOUT_US, $data->toArray(), 'json', 'cms');
        $aboutUs = $settingsService->get(SettingKeyEnum::ABOUT_US, AboutUsData::getDefaults());

        return response()->success(
            AboutUsData::from($aboutUs),
            __('messages.updated', ['model' => __('messages.models.about_us')])
        );
    }
}
