<?php

declare(strict_types=1);

namespace App\Actions\Admin\Slider;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Models\Slider;
use Illuminate\Support\Facades\DB;

final class DeleteSliderAction
{
    public function __construct(private readonly CacheStore $cache) {}

    public function handle(Slider $slider): void
    {
        DB::transaction(function () use ($slider): void {
            $image = $slider->getMedia('image')->first();
            $slider->delete();
            if ($image) {
                $image->delete();
            }
        });

        $this->cache->forget(CacheKey::Slider);
    }
}
