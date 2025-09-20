<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\HeaderCreateData;
use App\Data\Admin\Settings\HeaderData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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
     * @responseFile 200 responses/settings/header.json
     */
    public function show(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Setting::class);

        $header = Setting::get('header', HeaderData::getDefaults());

        return response()->success(HeaderData::from($header));
    }

    /**
     * Update header settings.
     *
     * @responseFile 200 responses/settings/header.json
     * @responseFile 422 responses/422.json
     */
    public function update(HeaderCreateData $data): ApiResponseInterface
    {
        Gate::authorize('update', Setting::class);

        Setting::set('header', $data->toArray(), 'json', 'header');
        $header = Setting::get('header');
        $links = $header['navigation_links'] ?? [];
        // Sort links by 'order' key
        usort($links, fn ($a, $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        // Reindex array
        $header['navigation_links'] = array_values($links);
        return response()->success(
            HeaderData::from($header),
            __('messages.updated', ['model' => __('messages.models.header')])
        );
    }
}
