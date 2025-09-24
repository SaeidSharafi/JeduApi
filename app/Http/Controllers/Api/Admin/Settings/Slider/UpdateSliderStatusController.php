<?php

namespace App\Http\Controllers\Api\Admin\Settings\Slider;

use App\Actions\Admin\Slider\UpdateSliderStatusAction;
use App\Data\Admin\ChangeStatusData;
use App\Data\Admin\Slider\SliderData;
use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Support\Facades\Gate;

class UpdateSliderStatusController extends Controller
{
    public function __invoke(ChangeStatusData $data, Slider $slider, UpdateSliderStatusAction $action)
    {
        Gate::authorize('update', $slider);
        $updatedSlider = $action->handle($data, $slider);
        return response()->updated(data:SliderData::from($updatedSlider),model: $updatedSlider);
    }
}
