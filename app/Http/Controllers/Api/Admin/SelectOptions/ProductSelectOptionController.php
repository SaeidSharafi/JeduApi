<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\SelectOptions\ProductSelectOptionData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;

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
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/products.json
     */
    public function __invoke(?ProductableEnum $productableType = null): ApiResponseInterface
    {
        $query         = request()->string('q', '');
        $perPage       = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');
        $productsQuery = Product::query()
            ->publishedAndVisible()
            ->hasPublishedDeliveryOption()
            ->publishedProductable()
            ->activeTerm();
        if ($productableType) {
            $productsQuery->ofType($productableType);
        }

        $products = $productsQuery
            ->search($query->value())
            ->orderBy('short_name')
            ->paginate($perPage)
            ->withQueryString();

        return apiResponse()->success(ProductSelectOptionData::collect($products));
    }
}
