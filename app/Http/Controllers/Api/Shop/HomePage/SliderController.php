<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Cache\CacheStore;
use App\Data\Shop\HomePage\SliderData;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Support\Collection;

/**
 * @group Shop - Home Page
 *
 * APIs for retrieving Home Page Content
 */
final class SliderController extends Controller
{
    public function __construct(private readonly CacheStore $cache) {}

    /**
     * List Sliders
     *
     * Returns a list of active sliders to be displayed on the home page.
     *
     * @responseFile 200 resources/responses/shop/home/slider.json
     */
    public function __invoke(): ApiResponseInterface
    {
        $sliders = $this->cache->flexible(CacheKey::Slider, [],
            fn (): Collection => SliderData::collect(
                Slider::query()->active()->orderBy('order')->get()
            )
        );

        return apiResponse()->success($sliders);
    }
}
