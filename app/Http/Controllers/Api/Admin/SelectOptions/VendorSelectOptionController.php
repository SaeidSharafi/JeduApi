<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Term\VendorSelectOptionData;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 */
final class VendorSelectOptionController extends Controller
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
        $limit = request()->integer('limit', 10);

        $vendors = \App\Models\Vendor::query()
            ->when($query, function ($vendor) use ($query) {
                $vendor->where(function ($vendor) use ($query) {
                    $vendor
                        ->where('name', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->when($limit, fn(Builder $q): Builder => $q->limit($limit))
            ->get(['id', 'name', 'address', 'logo_url']);

        return response()->success(
            VendorSelectOptionData::collect($vendors)
        );
    }
}
