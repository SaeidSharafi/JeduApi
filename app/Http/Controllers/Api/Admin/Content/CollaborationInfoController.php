<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\CollaborationPageCreateData;
use App\Data\Admin\Settings\CollaborationPageData;
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
final class CollaborationInfoController extends Controller
{
    /**
     * Get collaboration settings.
     *
     * @responseFile 200 resources/responses/admin/settings/collaboration.json
     */
    public function show(SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return response()->success(
            CollaborationPageData::from($settingsService->get(SettingKeyEnum::COLLABORATION, CollaborationPageData::getDefaults()))
        );
    }

    /**
     * Update collaboration settings.
     *
     * @responseFile 200 resources/responses/admin/settings/collaboration.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(CollaborationPageCreateData $data, SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        $settingsService->set(SettingKeyEnum::COLLABORATION, $data->toArray(), 'json', 'cms');

        return response()->success(
            CollaborationPageData::from($settingsService->get(SettingKeyEnum::COLLABORATION, CollaborationPageData::getDefaults())),
            __('messages.updated', ['model' => __('messages.models.about_us')])
        );
    }
}
