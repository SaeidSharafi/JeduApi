<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\HomePage\PartnerData;
use App\Enums\Content\PartnerShowInEnum;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use SmartCache\Facades\SmartCache;

/**
 * @group Shop - Home Page
 *
 * APIs for retrieving Home Page Content
 */
final class PartnerController extends Controller
{
    /**
     * List Partners
     *
     * Display a listing of active partners, optionally filtered by their display location.
     *
     * You can filter partners by the `show_in` query parameter to get partners meant for specific sections of the site,
     *   can be one of the following values:
     * - `home`: Partners to be displayed on the home page.
     * - `course`: Partners to be displayed on course-related pages.
     *
     * @response {
     *      "message": "عملیات با موفقیت انجام شد.",
     *      "data": [
     *          {
     *          "title": "عنوان",
     *          "caption": "متن",
     *          "image_url": "https://jedu.ir/storage/fake-media/placeholder1.mp4",
     *          "url": "https://example.com/",
     *          "order": 77
     *          }
     *      ],
     *      "metadata": []
     * }
     */
    public function __invoke(Request $request): ApiResponseInterface
    {
        $showIn   = $request->query('show_in');
        $cacheKey = PartnerShowInEnum::getCacheKey($showIn);
        $partners = SmartCache::remember($cacheKey->value, $cacheKey->ttl(),
            function () use ($showIn) {
                $partners = Partner::query()
                    ->active()
                    ->when($showIn && PartnerShowInEnum::tryFrom($showIn),
                        fn ($query) => $query->where('show_in', $showIn))
                    ->orderBy('order')
                    ->get();

                return PartnerData::collect($partners);
            });

        return response()->success($partners);
    }
}
