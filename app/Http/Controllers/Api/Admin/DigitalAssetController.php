<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\DigitalAsset\CreateDigitalAssetAction;
use App\Actions\DigitalAsset\DeleteDigitalAssetAction;
use App\Actions\DigitalAsset\UpdateDigitalAssetAction;
use App\Contracts\ApiResponseInterface;
use App\Data\File\CreateDigitalAssetData;
use App\Data\File\DigitalAssetListItemData;
use App\Data\File\ShowDigitalAssetData;
use App\Data\PrivateFileData;
use App\Http\Controllers\Controller;
use App\Models\DigitalAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Plank\Mediable\Media;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Digital Asset Management
 *
 * APIs for managing digital assets
 *
 * @authenticated Admin
 */
final class DigitalAssetController extends Controller
{
    /**
     * Display a listing of the digital assets.
     *
     * This endpoint returns a paginated list of digital assets with optional filtering and sorting.
     * You can filter by name, slug, description, status, and whether the asset is attachable to a course.
     * You can also sort the results by name, slug, or status.
     *
     * @queryParam page int Page number to retrieve. Default is 1.
     * @queryParam per_page int Number of items per page. Default is 15.
     * @queryParam filter[name] string Filter by asset name.
     * @queryParam filter[slug] string Filter by asset slug.
     * @queryParam filter[status] string Filter by asset status (e.g., published, draft).
     * @queryParam filter[is_attachable_to_course] boolean Filter by whether the asset is attachable to a course.
     * @queryParam sort string Sort by asset name, slug, or status. Prefix with '-' for descending order (e.g., -name).
     *
     * @responseFile 200 responses/digital-asset/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', DigitalAsset::class);
        $files = QueryBuilder::for(DigitalAsset::class)
            ->allowedFilters(['name', 'slug', 'status',
                AllowedFilter::exact('is_attachable_to_course'),
            ])
            ->allowedSorts(['name', 'slug', 'status'])
            ->paginate();

        return response()->success(
            DigitalAssetListItemData::collect($files)
        );
    }

    /**
     * Store a newly created digital asset in database.
     *
     * @response 201
     */
    public function store(CreateDigitalAssetData $data, CreateDigitalAssetAction $action): ApiResponseInterface
    {
        Gate::authorize('create', DigitalAsset::class);
        $action->handle($data);

        return response()->created();
    }

    /**
     * Display the specified file.
     *
     * @responseFile 200 responses/digital-asset/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function show(DigitalAsset $digitalAsset): ApiResponseInterface
    {
        Gate::authorize('view', $digitalAsset);
        $media = [];
        foreach (['main', 'preview'] as $tag) {
            $media[$tag] = $digitalAsset->getMedia($tag)
                ->map(fn (Media $m): PrivateFileData => PrivateFileData::fromModel($m, $tag))
                ->toArray();
        }

        return response()->success(ShowDigitalAssetData::from([
            ...$digitalAsset->toArray(),
            'categories'  => $digitalAsset->categories,
            'attachments' => $media,
        ]));
    }

    /**
     * Update the specified file in database.
     *
     * @responseFile 200 responses/digital-asset/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function update(CreateDigitalAssetData $request, DigitalAsset $digitalAsset, UpdateDigitalAssetAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $digitalAsset);
        $action->handle($request, $digitalAsset);
        $digitalAsset->refresh();
        $media = [];
        foreach (['main', 'preview'] as $tag) {
            $media[$tag] = $digitalAsset->getMedia($tag)
                ->map(fn (Media $m): PrivateFileData => PrivateFileData::fromModel($m, $tag))
                ->toArray();
        }

        return response()->success(
            ShowDigitalAssetData::from([
                ...$digitalAsset->toArray(),
                'categories'  => $digitalAsset->categories,
                'attachments' => $media,
            ])
        );
    }

    /**
     * Remove the specified file from database.
     *
     * @response 204
     */
    public function destroy(DigitalAsset $digitalAsset, DeleteDigitalAssetAction $action): JsonResponse
    {
        Gate::authorize('delete', $digitalAsset);
        $action->handle($digitalAsset);

        return response()->noContentJson();
    }
}
