<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\VendorSelectOptionData;
use App\Http\Controllers\Controller;

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
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/vendor.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $query   = request()->string('q', '');
        $perPage = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

        $vendors = \App\Models\Vendor::query()
            ->when($query, function ($vendor) use ($query): void {
                $vendor->where(function ($vendor) use ($query): void {
                    $vendor
                        ->whereLike('name', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'address', 'logo_url'])
            ->withQueryString();

        return apiResponse()->success(
            VendorSelectOptionData::collect($vendors)
        );
    }
}
