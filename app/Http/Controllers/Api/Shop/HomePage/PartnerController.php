<?php

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Data\Shop\HomePage\PartnerData;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

/**
 * @group Shop - Home Page Management
 *
 * APIs for managing home page content in the shop
 */
class PartnerController extends Controller
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
    public function __invoke(Request $request)
    {
        $showIn = $request->query('show_in');
        $partners = Partner::query()
            ->active()
            ->when($showIn, fn($query) => $query->where('show_in', $showIn))
            ->orderBy('order')
            ->get();

        return response()->success(PartnerData::collect($partners));
    }
}
