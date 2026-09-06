<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Actions\Admin\Bundle\CreateBundleAction;
use App\Actions\Admin\Bundle\DeleteBundleAction;
use App\Actions\Admin\Bundle\UpdateBundleAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Bundle\BundleCreateData;
use App\Data\Admin\Bundle\BundleData;
use App\Data\Admin\Bundle\BundleUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

final class BundleController extends Controller
{
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Bundle::class);

        $bundles = QueryBuilder::for(Bundle::class)
            ->allowedFilters(['slug', 'full_name', 'short_name', 'status'])
            ->allowedSorts(['slug', 'full_name', 'short_name', 'status', 'created_at', 'updated_at'])
            ->defaultSort('-created_at')
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return apiResponse()->success(BundleData::collect($bundles));
    }

    public function store(BundleCreateData $data, CreateBundleAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Bundle::class);

        return apiResponse()->created(BundleData::from($action->handle($data)), model: Bundle::class);
    }

    public function show(Bundle $bundle): ApiResponseInterface
    {
        Gate::authorize('view', $bundle);

        return apiResponse()->success(BundleData::from($bundle));
    }

    public function update(BundleUpdateData $data, Bundle $bundle, UpdateBundleAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $bundle);

        return apiResponse()->updated(BundleData::from($action->handle($data, $bundle)), model: Bundle::class);
    }

    public function destroy(Bundle $bundle, DeleteBundleAction $action): ApiResponseInterface
    {
        Gate::authorize('delete', $bundle);
        $action->handle($bundle);

        return apiResponse()->noContentJson();
    }
}
