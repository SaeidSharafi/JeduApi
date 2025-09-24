<?php

namespace App\Http\Controllers\Api\Shop\Shared;

use App\Data\Admin\Settings\FooterData as AdminFooterData;
use App\Data\Shop\Shared\FooterData;
use App\Http\Controllers\Controller;
use App\Models\Setting;

class FooterController extends Controller
{
    public function __invoke()
    {
        $footer = Setting::getValue('footer', AdminFooterData::class::getDefaults());
        return response()->success(FooterData::from($footer));
    }
}
