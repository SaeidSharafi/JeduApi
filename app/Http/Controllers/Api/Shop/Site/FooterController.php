<?php

namespace App\Http\Controllers\Api\Shop\Site;

use App\Data\Admin\Settings\FooterData as AdminFooterData;
use App\Data\Shop\Site\FooterData;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsService;

/**
 * @group Shop - Site
 *
 * API for retrieving site configuration
 *
 */
class FooterController extends Controller
{
    /**
     * Retrieve website footer configuration.
     *
     * Returns the footer settings (falls back to admin defaults) and returns them as a FooterData payload
     * inside a successful JSON response.
     *
     * @return \Illuminate\Http\JsonResponse JSON success response containing the FooterData payload.
     */
    public function __invoke(SettingsService $service)
    {
        $footer = $service->get('footer', AdminFooterData::class::getDefaults());
        return response()->success(FooterData::from($footer));
    }
}
