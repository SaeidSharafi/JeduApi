<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\FooterCreateData;
use App\Data\Admin\Settings\FooterData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Plank\Mediable\Media;

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
     * @responseFile 200 responses/settings/footer.json
     */
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $footer = Setting::getValue('footer', FooterData::getDefaults());

        return response()->success(FooterData::from($footer));
    }

    /**
     * Update the stored footer settings.
     *
     * Authorizes the current user for the 'update' action on the Setting model, persists the provided
     * footer data under the 'footer' setting (as JSON), and, if a logo ID is included, resolves the
     * corresponding Media record and synchronizes it to the setting under the 'logo' collection.
     *
     * Returns a success API response containing the updated FooterData.
     *
     * @param FooterCreateData $data Validated DTO containing footer fields; may include a `logo` media ID.
     * @return ApiResponseInterface Success response with the updated FooterData.
     *
     * @responseFile 200 responses/settings/footer.json
     * @responseFile 422 responses/422.json
     */
    public function update(FooterCreateData $data): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        $logo = null;
        $validated = $data->toArray();
        if ($data->logo !== null) {
            $logo = Media::find($data->logo);
        }
        $validated['logo_url'] = $logo?->getUrl() ?? null;
        $validated['logo_alt'] = $logo?->alt ?? null;

        $setting = Setting::setValue('footer', $validated, 'json', 'footer');
        $setting->syncMedia($logo,'logo');
        $footer = Setting::getValue('footer');

        return response()->success(
            FooterData::from($footer),
            __('messages.updated', ['model' => __('messages.models.footer')])
        );
    }
}
