<?php

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
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
            ->allowedSorts(['created_at','updated_at', 'name', 'short_name', 'status', 'is_visible', 'is_featured'])
            ->defaultSort('-created_at')
            ->with(['term','productable', 'vendor'])
            ->paginate(request()->input('perPage', 15))
            ->withQueryString();

        return response()->success(ProductListItemData::collect($products));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductCreateData $data, CreateProductAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Product::class);
        $product = $action->handle($data);
        $product->load(['productableWithAllRelations','term']);
        return response()->created(data: ProductData::from($product), model: Product::class);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): ApiResponseInterface
    {
        Gate::authorize('view', $product);
        $product->load(['productableWithAllRelations','term']);
        return response()->success(
            ProductData::from($product)->toArray()
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductUpdateData $data, Product $product, UpdateProductAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $product);
        $product = $action->handle($data,$product);
        $product->load(['productableWithAllRelations','term', 'categories']);
        return response()->updated(
            ProductData::from($product)->toArray(),
            model: Product::class
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product, DeleteProductAction $action): JsonResponse
    {
        Gate::authorize('delete', $product);
        $action->handle($product);
        return response()->noContentJson();
    }
}
