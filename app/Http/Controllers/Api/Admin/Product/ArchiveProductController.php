<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Contracts\ApiResponseInterface;
use App\Enums\PublicationStatusEnum;
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
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function __invoke(Product $product): ApiResponseInterface
    {
        Gate::authorize('update', $product);

        $product->update(['status' => PublicationStatusEnum::ARCHIVED]);

        return response()->success(message: __('messages.product.acrhived'));
    }
}
