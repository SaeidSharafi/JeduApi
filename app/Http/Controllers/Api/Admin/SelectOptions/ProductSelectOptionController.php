<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\SelectOptions\ProductSelectOptionData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Services\Shop\ProductQueryService;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 *
 * retrieve a list of products for select options
 */
final class ProductSelectOptionController extends Controller
{
    /**
     * Products list
     *
     * @urlParam productableType string|null The type of productable items to include. Possible values: course, seminar, digital_asset. Example: "course"
     *
     * @queryParam  q string The search query for filtering products (match name or SKU). Example: "advanced"
     * @queryParam  limit integer The maximum number of results to return. Default is 15. Example: 10
     *
     * @responseFile 200 responses/select-options/products.json
     */
    public function __invoke(ProductQueryService $service, ?ProductableEnum $productableType = null): ApiResponseInterface
    {
        $query         = request()->string('q', '');
        $limit         = request()->integer('limit', 15);
        $productsQuery = $service->availableProducts();
        if ($productableType) {
            $productsQuery->ofType($productableType);
        }

        $products = $productsQuery
            ->search($query->value())
            ->sortBy('short_name', 'asc')
            ->limit($limit)
            ->get();

        return response()->success(ProductSelectOptionData::collect($products));
    }
}
