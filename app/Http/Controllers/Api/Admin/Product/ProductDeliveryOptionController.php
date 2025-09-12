<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Actions\Admin\ProductDeliveryOption\CreateProductDeliveryOptionAction;
use App\Actions\Admin\ProductDeliveryOption\DeleteProductDeliveryOptionAction;
use App\Actions\Admin\ProductDeliveryOption\UpdateProductDeliveryOptionAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionCreateData;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Product Delivery Option Management
 *
 * APIs for managing product delivery options
 *
 * @authenticated Staff
 */
final class ProductDeliveryOptionController extends Controller
{
    /**
     * Get a list of delivery options for a product.
     *
     * @responseFile 200 responses/product-delivery-option/index.json
     */
    public function index(Product $product): ApiResponseInterface
    {
        Gate::authorize('view-any', ProductDeliveryOption::class);
        $deliveryOptions = $product->productDeliveryOptions()->with('teachers')->get();

        return response()->success(ProductDeliveryOptionShowData::collect($deliveryOptions));
    }

    /**
     * Create a new delivery option for a product.
     *
     * @responseFile 201 responses/201.json
     */
    public function store(
        ProductDeliveryOptionCreateData $data,
        Product $product,
        CreateProductDeliveryOptionAction $action
    ): ApiResponseInterface {
        Gate::authorize('create', ProductDeliveryOption::class);
        $deliveryOption = $action->handle($data, $product);
        $deliveryOption->loadMissing('teachers');

        return response()->created(
            ProductDeliveryOptionShowData::from($deliveryOption),
            model: ProductDeliveryOption::class,
        );
    }

    /**
     * Get the specified delivery option details.
     *
     * @responseFile 200 responses/product-delivery-option/show.json
     * @responseFile 404 responses/404.json
     */
    public function show(Product $product, ProductDeliveryOption $deliveryOption): ApiResponseInterface
    {
        Gate::authorize('view', $deliveryOption);
        $deliveryOption->loadMissing('teachers');

        return response()->success(ProductDeliveryOptionShowData::from($deliveryOption));
    }

    /**
     * Update the specified delivery option.
     *
     * @responseFile 200 responses/product-delivery-option/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function update(
        ProductDeliveryOptionUpdateData $data,
        Product $product,
        ProductDeliveryOption $deliveryOption,
        UpdateProductDeliveryOptionAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $deliveryOption);
        $deliveryOption = $action->handle($data, $deliveryOption);
        $deliveryOption->loadMissing('teachers');

        return response()->updated(
            ProductDeliveryOptionShowData::from($deliveryOption),
            model: ProductDeliveryOption::class,
        );
    }

    /**
     * Remove the specified delivery option.
     *
     * @response 204
     *
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function destroy(
        Product $product,
        ProductDeliveryOption $deliveryOption,
        DeleteProductDeliveryOptionAction $action
    ): JsonResponse {
        Gate::authorize('delete', $deliveryOption);
        $action->handle($deliveryOption);

        return response()->noContentJson();
    }
}
