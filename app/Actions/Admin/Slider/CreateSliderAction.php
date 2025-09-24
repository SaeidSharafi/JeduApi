<?php

declare(strict_types=1);

namespace App\Actions\Admin\Slider;

use App\Data\Admin\Slider\SliderCreateData;
use App\Models\Slider;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final class CreateSliderAction
{
    public function handle(SliderCreateData $data): Slider
    {
        return DB::transaction(function () use ($data): Slider {
            $image = null;
            if ($data->image) {
                $image = Media::find($data->image);
            }
            $sliderData = [
                ...$data->except('image')->toArray(),
                'image_url' => $image?->getUrl(),
                'image_alt' => $data->title,
            ];
            $slider = Slider::query()->create($sliderData)->fresh();
            $slider->syncMedia($image, 'image');

            $slider->refresh();

            return $slider;
        });
    }
}
