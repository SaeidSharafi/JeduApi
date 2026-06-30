<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Actions\Admin\Settings\UpdateHeaderSettingAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\HeaderCreateData;
use App\Data\Admin\Settings\HeaderData;
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
final class HeaderController extends Controller
{
    /**
     * Get header settings.
     *
     * @responseFile 200 resources/responses/admin/settings/header.json
     */
    public function show(SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return apiResponse()->success(
            HeaderData::from($settingsService->get(SettingKeyEnum::HEADER, HeaderData::getDefaults()))
        );
    }

    /**
     * Update header settings.
     *
     * @responseFile 200 resources/responses/admin/settings/header.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(HeaderCreateData $data, UpdateHeaderSettingAction $action): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        return apiResponse()->success(
            $action->handle($data),
            __('messages.updated', ['model' => __('messages.models.header')])
        );
    }
}
