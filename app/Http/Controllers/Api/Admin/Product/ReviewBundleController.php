<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Actions\Admin\ProductDeliveryOption\ReviewBundleAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Product Delivery Option Management
 *
 * Review bundle composition of product delivery options
 *
 * @authenticated Staff
 */
final class ReviewBundleController extends Controller
{
    /**
     * Review the bundle composition of the specified delivery option.
     *
     * @responseFile 200 resources/responses/admin/product-delivery-option/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     */
    public function __invoke(
        Product $product,
        ProductDeliveryOption $deliveryOption,
        ReviewBundleAction $action,
    ): ApiResponseInterface {
        abort_unless($deliveryOption->product_id === $product->id, 404);
        Gate::authorize('update', $deliveryOption);

        return apiResponse()->updated(
            ProductDeliveryOptionShowData::from($action->handle($deliveryOption)),
            model: ProductDeliveryOption::class,
        );
    }
}
