<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Actions\Admin\Product\ArchiveProductAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Product Management
 *
 * APIs for managing products in the admin panel.
 *
 * @authenticated Staff
 */
final class ArchiveProductController extends Controller
{
    /**
     * Archive the specified product.
     *
     * Archives a product, changing its status to 'archived'.
     *
     *
     * @response {
     *  "message": "Product archived successfully."
     * }
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(Product $product, ArchiveProductAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $product);

        $action->handle($product);

        return apiResponse()->success(message: __('messages.product.acrhived'));
    }
}
