<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\HomePage\SliderData;
use App\Enums\System\CacheKeysEnum;
use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Services\SWRCacheService;
use Illuminate\Support\Collection;

/**
 * @group Shop - Home Page
 *
 * APIs for retrieving Home Page Content
 */
final class SliderController extends Controller
{
    /**
     * List Sliders
     *
     * Returns a list of active sliders to be displayed on the home page.
     *
     * @responseFile 200 resources/responses/shop/home/slider.json
     */
    public function __invoke(): ApiResponseInterface
    {
        $sliders = SWRCacheService::rememberHomepageContent(CacheKeysEnum::Slider->value,
            fn (): Collection => SliderData::collect(
                Slider::query()->active()->orderBy('order')->get()
            )
        );

        return apiResponse()->success($sliders);
    }
}
