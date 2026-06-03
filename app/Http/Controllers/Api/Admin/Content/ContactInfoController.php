<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\ContactInfoData;
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
final class ContactInfoController extends Controller
{
    /**
     * Get contact info settings.
     *
     * @responseFile 200 resources/responses/admin/settings/contact-info.json
     */
    public function show(SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        return response()->success(
            ContactInfoData::from($settingsService->get(SettingKeyEnum::CONTACT_INFO, ContactInfoData::getDefaults()))
        );
    }

    /**
     * Update contact info settings.
     *
     * @responseFile 200 resources/responses/admin/settings/contact-info.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(ContactInfoData $data, SettingsService $settingsService): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        $settingsService->set(SettingKeyEnum::CONTACT_INFO, $data->toArray(), 'json', 'cms');

        return response()->success(
            ContactInfoData::from($settingsService->get(SettingKeyEnum::CONTACT_INFO, ContactInfoData::getDefaults())),
            __('messages.updated', ['model' => __('messages.models.contact_info')])
        );
    }
}
