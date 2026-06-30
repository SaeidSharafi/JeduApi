<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\SettingData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Settings Management
 *
 * @authenticated
 */
final class SettingController extends Controller
{
    /**
     * Get all settings grouped by category.
     *
     * @responseFile 200 resources/responses/admin/settings/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $settings = Setting::all()
            ->map(fn (Setting $setting): SettingData => SettingData::fromModel($setting))
            ->groupBy('group');

        return apiResponse()->success($settings);
    }
}
