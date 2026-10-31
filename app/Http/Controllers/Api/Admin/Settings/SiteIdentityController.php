<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Actions\Admin\Settings\UpdateSiteIdentityAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\SiteIdentityData;
use App\Data\Admin\Settings\SiteIdentityUpdateData;
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
final class SiteIdentityController extends Controller
{
    /**
     * Get the site identity.
     *
     * @responseFile 200 resources/responses/admin/settings/site-identity.json
     */
    public function show(SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return apiResponse()->success(
            SiteIdentityData::from(
                $settingsService->get(SettingKeyEnum::SITE_IDENTITY, SiteIdentityData::getDefaults())
            )
        );
    }

    /**
     * Update the site identity.
     *
     * @responseFile 200 resources/responses/admin/settings/site-identity.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(SiteIdentityUpdateData $data, UpdateSiteIdentityAction $action): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        return apiResponse()->success(
            $action->handle($data),
            __('messages.updated', ['model' => __('messages.models.site_identity')])
        );
    }
}
