<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\ContactInfoData;
use App\Enums\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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
     * @responseFile 200 responses/settings/contact-info.json
     */
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $contactInfo = Setting::getValue(SettingKeyEnum::CONTACT_INFO, ContactInfoData::getDefaults());

        return response()->success(ContactInfoData::from($contactInfo));
    }

    /**
     * Update contact info settings.
     *
     * @responseFile 200 responses/settings/contact-info.json
     * @responseFile 422 responses/422.json
     */
    public function update(ContactInfoData $data): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        Setting::setValue(SettingKeyEnum::CONTACT_INFO, $data->toArray(), 'json', 'cms');
        $contactInfo = Setting::getValue(SettingKeyEnum::CONTACT_INFO);
        return response()->success(
            ContactInfoData::from($contactInfo),
            __('messages.updated', ['model' => __('messages.models.contact_info')])
        );
    }
}
