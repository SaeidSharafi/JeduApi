<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Actions\Admin\Settings\UpdateFooterSettingAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\FooterCreateData;
use App\Data\Admin\Settings\FooterData;
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
final class FooterController extends Controller
{
    /**
     * Get footer settings.
     *
     * @responseFile 200 resources/responses/admin/settings/footer.json
     */
    public function show(SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return response()->success(
            FooterData::from($settingsService->get(SettingKeyEnum::FOOTER, FooterData::getDefaults()))
        );
    }

    /**
     * Update footer settings.
     *
     * @responseFile 200 resources/responses/admin/settings/footer.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(FooterCreateData $data, UpdateFooterSettingAction $action): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        return response()->success(
            $action->handle($data),
            __('messages.updated', ['model' => __('messages.models.footer')])
        );
    }
}
