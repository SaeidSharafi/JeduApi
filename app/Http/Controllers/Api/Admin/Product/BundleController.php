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

/**
 * @group Admin - Bundle Management
 *
 * APIs for managing courses
 *
 * @authenticated Staff
 */
final class BundleController extends Controller
{
    /**
     * return a list of the bundles.
     *
     * @queryParam filter[slug] string Filter by bundle slug. Example: bundle-101
     * @queryParam filter[full_name] string Filter by bundle full name. Example: Full Stack Package
     * @queryParam filter[short_name] string Filter by bundle short name. Example: FULLSTACK
     * @queryParam filter[status] string Filter by bundle status. Example: published
     * @queryParam sort string Sort by a field. Allowed values: slug, short_name, status, created_at, updated_at.
     *     Prefix with '-' for descending order (e.g., -created_at). Example: -created_at
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 resources/responses/admin/bundle/index.json
     */
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

    /**
     * Create a new bundle.
     *
     * @responseFile 201 resources/responses/admin/bundle/show.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(BundleCreateData $data, CreateBundleAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Bundle::class);
        $bundle = $action->handle($data);
        $bundle->loadMediaWithVariantsMatchAll();
        $media = $bundle->getAllMedia();

        return apiResponse()->created(BundleData::from([
            ...$bundle->toArray(),
            'media' => $media,
        ]), model: Bundle::class);
    }

    /**
     * Display the specified bundle.
     *
     * @responseFile 200 resources/responses/admin/bundle/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function show(Bundle $bundle): ApiResponseInterface
    {
        Gate::authorize('view', $bundle);
        $bundle->loadMediaWithVariantsMatchAll();
        $media = $bundle->getAllMedia();

        return apiResponse()->success(BundleData::from([
            ...$bundle->toArray(),
            'media' => $media,
        ]));
    }

    /**
     * Update the specified bundle.
     *
     * @responseFile 200 resources/responses/admin/bundle/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     * @responseFile 403 resources/responses/403.json
     */
    public function update(BundleUpdateData $data, Bundle $bundle, UpdateBundleAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $bundle);

        $updatedBundle = $action->handle($data, $bundle);
        $updatedBundle->loadMediaWithVariantsMatchAll();

        return apiResponse()->updated(BundleData::from([
            ...$updatedBundle->toArray(),
            'media' => $updatedBundle->getAllMedia(),
        ]), model: Bundle::class);
    }

    /**
     * Remove the specified bundle.
     *
     * @response 204
     *
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function destroy(Bundle $bundle, DeleteBundleAction $action): ApiResponseInterface
    {
        Gate::authorize('delete', $bundle);
        $action->handle($bundle);

        return apiResponse()->noContentJson();
    }
}
