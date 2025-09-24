<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\HomePage\SliderData;
use App\Enums\CacheKeysEnum;
use App\Http\Controllers\Controller;
use App\Models\Slider;
use SmartCache\Facades\SmartCache;

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
     * @response 200 {
     *  "message": "عملیات با موفقیت انجام شد.",
     *  "data": [
     *      {
     *          "title": "Est inventore ab ut praesentium.",
     *          "caption": "Saepe tempore velit consequatur velit doloremque commodi.",
     *          "image_url": "https://via.placeholder.com/800x600.png/00ddee?text=nature+voluptas",
     *          "image_alt": "Ipsam amet quos eos voluptas officiis pariatur nulla.",
     *          "link": null,
     *          "order": 42
     *      },
     *      {
     *          "title": "Nobis sit consequatur accusantium dolores.",
     *          "caption": "Hic est quidem unde pariatur officiis doloremque.",
     *          "image_url": "https://via.placeholder.com/800x600.png/00aaaa?text=nature+non",
     *          "image_alt": "Exercitationem sunt aliquid natus vel natus reprehenderit.",
     *          "link": null,
     *          "order": 46
     *      },
     *  ],
     *  "metadata": []
     * }
     */
    public function __invoke(): ApiResponseInterface
    {
        $sliders = SmartCache::remember(CacheKeysEnum::Slider->value, CacheKeysEnum::Slider->ttl(),
            fn () => SliderData::collect(
                Slider::query()->active()->orderBy('order')->get()
            )
        );

        return response()->success($sliders);
    }
}
