<?php

declare(strict_types=1);

namespace App\Actions\Admin\Slider;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Slider\SliderCreateData;
use App\Enums\System\CacheKey;
use App\Models\Slider;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final class UpdateSliderAction
{
    public function __construct(private readonly CacheStore $cache) {}

    public function handle(Slider $slider, SliderCreateData $data): Slider
    {
        $slider = DB::transaction(function () use ($slider, $data): Slider {
            $image = null;
            if ($data->image) {
                $image = Media::find($data->image);
            }
            $sliderData = [
                ...$data->except('image')->toArray(),
                'image_url' => $image ? $image->getUrl() : null,
                'image_alt' => $image ? $data->title : null,
            ];
            $slider->update($sliderData);
            $slider->syncMedia($image, 'image');

            return $slider->refresh();
        });

        $this->cache->forget(CacheKey::Slider);

        return $slider;
    }
}
