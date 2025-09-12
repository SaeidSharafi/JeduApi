<?php

namespace App\Actions\Admin\Slider;

use App\Data\Admin\Slider\SliderCreateData;
use App\Models\Slider;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

class DeleteSliderAction
{
    public function handle(Slider $slider): void
    {
        DB::transaction(function () use ($slider): void {
            $image = $slider->getMedia('image')->first();
            $slider->delete();
            if ($image) {
                $image->delete();
            }
        });
    }
}
