<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\HeaderCreateData;
use App\Data\Admin\Settings\HeaderData;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Plank\Mediable\Media;

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

        $header = Setting::getValue(SettingKeyEnum::HEADER, HeaderData::getDefaults());

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

        $header = $data->toArray();
        // Sort links by 'order' key
        $links = $header['navigation_links'] ?? [];
        usort($links, fn ($a, $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        $header['navigation_links'] = array_values($links);
        $logo                       = $data->logo ? Media::find($data->logo) : null;
        $header['logo_url']         = $logo?->getUrl();
        $setting                    = Setting::setValue(SettingKeyEnum::HEADER, $header, 'json', 'site');
        // Handle logo media
        $setting->syncMedia($logo, 'logo');

        return response()->success(
            HeaderData::from(
                Setting::getValue(SettingKeyEnum::HEADER, HeaderData::getDefaults())
            ),
            __('messages.updated', ['model' => __('messages.models.header')])
        );
    }
}
