<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Product\CreateProductAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Product\ProductCreateData;
use App\Data\Product\ProductData;
use App\Data\Product\ProductListItemData;
use App\Data\Product\ProductUpdateData;
use App\Enums\PublicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Product Management
 *
 * APIs for managing products in the admin panel.
 *
 * @authenticated Staff
 */
final class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     *
     * @queryParam filter[name] string Filter by product name. Example: Book
     * @queryParam filter[short_name] string Filter by product short name. Example: BK
     * @queryParam filter[is_visible] boolean Filter by visibility. Example: 1
     * @queryParam filter[is_featured] boolean Filter by featured status. Example: 0
     * @queryParam filter[status] string Filter by publication status. Example: published
     * @queryParam sort string Sort by a field. Allowed values: created_at, updated_at, name, short_name, status, is_visible, is_featured. Prefix with '-' for descending order. Example: -created_at
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam perPage integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/product/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Product::class);
        $products = QueryBuilder::for(Product::class)
            ->allowedIncludes(['productableWithAllRelations', 'term'])
            ->allowedFilters([
                'name', 'short_name',
                AllowedFilter::exact('is_visible'),
                AllowedFilter::exact('is_featured'),
                AllowedFilter::exact('status')->default(PublicationStatusEnum::PUBLISHED),
            ])
            ->allowedSorts(['created_at', 'updated_at', 'name', 'short_name', 'status', 'is_visible', 'is_featured'])
            ->defaultSort('-created_at')
            ->with(['term', 'productable', 'vendor'])
            ->paginate(request()->input('perPage', 15))
            ->withQueryString();

        return response()->success(ProductListItemData::collect($products));
    }

    /**
     * Store a newly created product in storage.
     *
     * @responseFile 201 responses/201.json
     * @responseFile 422 responses/422.json
     */
    public function store(ProductCreateData $data, CreateProductAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Product::class);
        $product = $action->handle($data);
        $product->load(['productableWithAllRelations', 'term']);

        return response()->created(data: ProductData::from($product), model: Product::class);
    }

    /**
     * Display the specified product.
     *
     * @responseFile 200 responses/product/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function show(Product $product): ApiResponseInterface
    {
        Gate::authorize('view', $product);
        $product->load(['productableWithAllRelations', 'term']);

        return response()->success(
            ProductData::from($product)->toArray()
        );
    }

    /**
     * Update the specified product in storage.
     *
     * @responseFile 200 responses/product/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function update(ProductUpdateData $data, Product $product, UpdateProductAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $product);
        $product = $action->handle($data, $product);
        $product->load(['productableWithAllRelations', 'term', 'categories']);

        return response()->updated(
            ProductData::from($product)->toArray(),
            model: Product::class
        );
    }

    /**
     * Remove the specified product from storage.
     *
     * @response 204
     *
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function destroy(Product $product, DeleteProductAction $action): JsonResponse
    {
        Gate::authorize('delete', $product);
        $action->handle($product);

        return response()->noContentJson();
    }
}
