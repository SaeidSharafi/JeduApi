<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Data\Shop\HomePage\HeaderData;
use App\Http\Controllers\Controller;
use App\Models\Setting;

final class HeaderController extends Controller
{
    public function __invoke()
    {
        $header = Setting::getValue('header', \App\Data\Admin\Settings\HeaderData::class::getDefaults());

        return response()->success(HeaderData::from($header));
    }
}
