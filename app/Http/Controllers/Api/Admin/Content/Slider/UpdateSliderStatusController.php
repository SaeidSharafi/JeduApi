<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content\Slider;

use App\Actions\Admin\Slider\UpdateSliderStatusAction;
use App\Data\Admin\ChangeStatusData;
use App\Data\Admin\Slider\SliderData;
use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Settings - Slider
 *
 * APIs for managing sliders
 */
final class UpdateSliderStatusController extends Controller
{
    /**
     * Update Slider Status
     *
     * Update the publication status of a specific slider.
     *
     * @responseFile 200 scenario="success" resources/responses/admin/settings/slider/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(ChangeStatusData $data, Slider $slider, UpdateSliderStatusAction $action)
    {
        Gate::authorize('update', $slider);
        $updatedSlider = $action->handle($data, $slider);

        return response()->updated(data: SliderData::from($updatedSlider), model: $updatedSlider);
    }
}
