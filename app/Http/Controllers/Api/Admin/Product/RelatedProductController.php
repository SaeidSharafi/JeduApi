<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Actions\Admin\RelatedProduct\CreateRelatedProductAction;
use App\Actions\Admin\RelatedProduct\DeleteRelatedProductAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Product\RelatedProductData;
use App\Data\Admin\Product\RelatedProductSyncData;
use App\Enums\Product\RelationTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Related Products Management
 *
 * APIs for managing related products (related, cross-sell, upsell)
 *
 * @authenticated Staff
 */
final class RelatedProductController extends Controller
{
    /**
     * Get all related products for a specific product.
     *
     * Returns all related products grouped by relation type.
     *
     * @queryParam relation_type string Filter by relation type (related, cross_sell, upsell). Example: cross_sell
     *
     * @responseFile 200 resources/responses/admin/related-products/index.json
     */
    public function index(Product $product): ApiResponseInterface
    {
        Gate::authorize('view', $product);

        $relationType = request()->input('relation_type');

        $query = $product->relatedProducts()
            ->with(['term', 'productable', 'vendor']);

        if ($relationType && RelationTypeEnum::tryFrom($relationType)) {
            $query->wherePivot('relation_type', $relationType);
        }

        $relatedProducts = $query->get();

        return apiResponse()->success(RelatedProductData::collect($relatedProducts));
    }

    /**
     * Sync related products for a specific product and relation type.
     *
     * This will replace all existing relations of the specified type with the new list.
     *
     * @responseFile 200 resources/responses/admin/related-products/index.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(Product $product, RelatedProductSyncData $data, CreateRelatedProductAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $product);

        $action->handle($product, $data);

        $relatedProducts = $product->relatedProducts()
            ->wherePivot('relation_type', $data->relation_type->value)
            ->get();

        return apiResponse()->created(data: RelatedProductData::collect($relatedProducts));
    }

    /**
     * Remove a specific related product relationship.
     *
     * @urlParam product_id integer required The ID of the product. Example: 1
     * @urlParam relatedProduct_id integer required The ID of the related product to remove. Example: 5
     *
     * @queryParam relation_type string required The relation type to remove (related, cross_sell, upsell). Example: cross_sell
     *
     * @response 204
     *
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function destroy(Product $product, Product $relatedProduct, DeleteRelatedProductAction $action): JsonResponse|ApiResponseInterface
    {
        Gate::authorize('update', $product);

        $relationType = request()->input('relation_type', 'invalid');
        $relationType = RelationTypeEnum::tryFrom($relationType);
        if (! $relationType) {
            return apiResponse()->validationError(__('validation.custom.product.related_product_type_invalid'));
        }

        $action->handle($product, $relationType, $relatedProduct);

        return apiResponse()->noContentJson();
    }
}
