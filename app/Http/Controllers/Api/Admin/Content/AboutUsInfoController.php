<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\AboutUsCreateData;
use App\Data\Admin\Settings\AboutUsData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $aboutUs           = Setting::getValue(SettingKeyEnum::ABOUT_US, AboutUsData::getDefaults());
        $aboutUs['images'] = Setting::witImages($aboutUs);

        return response()->success(AboutUsData::from($aboutUs));
    }

    /**
     * Update about us settings.
     *
     * @responseFile 200 responses/settings/about-us.json
     * @responseFile 422 responses/422.json
     */
    public function update(AboutUsCreateData $data): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        Setting::setValue(SettingKeyEnum::ABOUT_US, $data->toArray(), 'json', 'cms');
        $aboutUs = Setting::getValue(SettingKeyEnum::ABOUT_US);

        return response()->success(
            AboutUsData::from($aboutUs),
            __('messages.updated', ['model' => __('messages.models.about_us')])
        );
    }
}
