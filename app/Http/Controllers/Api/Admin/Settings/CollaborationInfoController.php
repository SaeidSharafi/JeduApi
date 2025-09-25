<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\AboutUsCreateData;
use App\Data\Admin\Settings\AboutUsData;
use App\Data\Admin\Settings\CollaborationPageCreateData;
use App\Data\Admin\Settings\CollaborationPageData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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
     * @responseFile 200 responses/settings/collaboration.json
     */
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $collaboration           = Setting::getValue('collaboration', CollaborationPageData::getDefaults());
        $collaboration['images'] = Setting::witImages($collaboration);

        return response()->success(CollaborationPageData::from($collaboration));
    }

    /**
     * Update collaboration settings.
     *
     * @responseFile 200 responses/settings/collaboration.json
     * @responseFile 422 responses/422.json
     */
    public function update(CollaborationPageCreateData $data): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        Setting::setValue('collaboration', $data->toArray(), 'json', 'about');
        $collaboration = Setting::getValue('collaboration');

        return response()->success(
            CollaborationPageData::from($collaboration),
            __('messages.updated', ['model' => __('messages.models.about_us')])
        );
    }
}
