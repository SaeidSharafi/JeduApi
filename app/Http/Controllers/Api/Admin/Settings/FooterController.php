<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\FooterCreateData;
use App\Data\Admin\Settings\FooterData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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
     * @responseFile 200 responses/settings/footer.json
     */
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $footer = Setting::get('footer', FooterData::getDefaults());

        return response()->success(FooterData::from($footer));
    }

    /**
     * Update footer settings.
     *
     * @responseFile 200 responses/settings/footer.json
     * @responseFile 422 responses/422.json
     */
    public function update(FooterCreateData $data): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        Setting::set('footer', $data->toArray(), 'json', 'footer');
        $footer = Setting::get('footer');

        return response()->success(
            FooterData::from($footer),
            __('messages.updated', ['model' => __('messages.models.footer')])
        );
    }
}
