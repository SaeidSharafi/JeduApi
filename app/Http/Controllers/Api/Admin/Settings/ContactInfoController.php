<?php

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\ContactInfoData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Settings Management
 *
 * @authenticated
 */
class ContactInfoController extends Controller
{
    /**
     * Get contact info settings.
     *
     * @responseFile 200 responses/settings/contact-info.json
     */
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $contactInfo = Setting::get('contact_info', ContactInfoData::getDefaults());

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

        Setting::set('contact_info', $data->toArray(), 'json', 'contact');

        return response()->success(
            $data->toArray(),
            __('messages.updated', ['model' => __('messages.models.contact_info')])
        );
    }
}
