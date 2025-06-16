<?php

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Category\CategorySelectOptionData;
use App\Data\Term\TermSelectOptionData;
use App\Data\Term\VendorSelectOptionData;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 */
class VendorSelectOptionController extends Controller
{

    /**
     * Vendors list
     *
     * @queryParam  q string The search query for filtering vendors (match name). Example: "vendor 1"
     *
     * @responseFile 200 responses/select-options/vendor.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');

        $vendors = \App\Models\Vendor::query()
            ->when($query, function ($vendor) use ($query) {
                $vendor->where(function ($vendor) use ($query) {
                    $vendor
                        ->where('name', 'like', '%'.$query.'%')
                    ;
                });
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'address', 'logo_url']);
        return response()->success(
            VendorSelectOptionData::collect($vendors)
        );
    }
}
