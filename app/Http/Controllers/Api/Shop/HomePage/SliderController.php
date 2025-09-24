<?php

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Data\Shop\HomePage\SliderData;
use App\Http\Controllers\Controller;
use App\Models\Slider;

class SliderController extends Controller
{
    public function __invoke()
    {
        $sliders = Slider::query()->active()->orderBy('order')->get();
        return response()->success(SliderData::collect($sliders));
    }
}
