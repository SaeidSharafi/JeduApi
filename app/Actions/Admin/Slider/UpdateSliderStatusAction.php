<?php

declare(strict_types=1);

namespace App\Actions\Admin\Slider;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\ChangeStatusData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\System\CacheKey;
use App\Models\Slider;

final class UpdateSliderStatusAction
{
    public function __construct(private readonly CacheStore $cache) {}

    public function handle(ChangeStatusData $data, Slider $slider): Slider
    {
        $slider->status = PublicationStatusEnum::from($data->status);
        $slider->save();

        $this->cache->forget(CacheKey::Slider);

        return $slider;
    }
}
