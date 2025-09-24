<?php

declare(strict_types=1);

namespace App\Actions\Admin\Slider;

use App\Data\Admin\ChangeStatusData;
use App\Models\Slider;

final class UpdateSliderStatusAction
{
    public function handle(ChangeStatusData $data, Slider $slider): Slider
    {
        $slider->status = $data->status;
        $slider->save();

        return $slider;
    }
}
