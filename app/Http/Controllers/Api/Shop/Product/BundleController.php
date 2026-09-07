<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Product;

use App\Actions\Shop\Bundle\ListBundlesAction;
use App\Actions\Shop\Bundle\ShowBundleAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\PaginationRequestData;
use App\Http\Controllers\Controller;
use App\Models\Product;

/**
 * @group Shop - Products - Bundles
 *
 * APIs for the dedicated Bundle storefront.
 */
final class BundleController extends Controller
{
    /**
     * List currently saleable Bundles using pagination only.
     *
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 resources/responses/shop/bundles/index.json
     */
    public function index(PaginationRequestData $data, ListBundlesAction $action): ApiResponseInterface
    {
        return apiResponse()->success($action->handle($data->page, $data->per_page));
    }

    /**
     * Retrieve a Bundle by its Product slug.
     *
     * @responseFile 200 resources/responses/shop/bundles/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(Product $product, ShowBundleAction $action): ApiResponseInterface
    {
        return apiResponse()->success($action->handle($product));
    }
}
