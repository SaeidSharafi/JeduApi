<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Vendor\CreateVendorAction;
use App\Actions\Admin\Vendor\DeleteVendorAction;
use App\Actions\Admin\Vendor\UpdateVendorAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\MediaData;
use App\Data\Admin\Vendor\CreateVendorData;
use App\Data\Admin\Vendor\ShowVendorData;
use App\Data\Admin\Vendor\VendorListItemData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Vendor
 *
 * APIs for managing vendors
 *
 * @authenticated
 */
final class VendorController extends Controller
{
    /**
     * Display a listing of vendors.
     *
     * @queryParam filter[name] string Filter vendors by name. Example: Acme Corp
     * @queryParam filter[email] string Filter vendors by email. Example: vendor@example.com
     * @queryParam filter[phone] string Filter vendors by phone number. Example: +1234567890
     *
     * @responseFile responses/vendor/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Vendor::class);
        $vendors = QueryBuilder::for(Vendor::class)
            ->allowedFilters(['name', 'email', 'phone'])
            ->allowedSorts(['name', 'created_at'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(VendorListItemData::collect($vendors));
    }

    /**
     * Store a newly created vendor in database.
     *
     * @response 201
     *
     * @responseFile 422 responses/422.json
     * @responseFile 403 responses/403.json
     */
    public function store(CreateVendorData $data, CreateVendorAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Vendor::class);
        $action->handle($data);

        return response()->created(model: Vendor::class);
    }

    /**
     * Display the specified vendor.
     * This endpoint returns detailed information about a specific vendor, including associated media.
     *
     * @responseFile responses/vendor/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(Vendor $vendor): ApiResponseInterface
    {
        Gate::authorize('view', $vendor);
        $media = $vendor->getAllMediaByTag()
            ->map(function ($item, $tag) {
                return $item->map(function ($mediaItem) use ($tag) {
                    return MediaData::fromModel($mediaItem, $tag);
                });
            })->toArray();

        return response()->success(ShowVendorData::from(
            [
                ...$vendor->toArray(),
                'media' => $media,
            ]
        ));
    }

    /**
     * Update the specified vendor in database.
     *
     * @responseFile responses/vendor/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function update(CreateVendorData $data, Vendor $vendor, UpdateVendorAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $vendor);
        $action->handle($data, $vendor);
        $vendor->refresh();
        $media = $vendor->getAllMediaByTag()
            ->map(function ($item, $tag) {
                return $item->map(function ($mediaItem) use ($tag) {
                    return MediaData::fromModel($mediaItem, $tag);
                });
            })->toArray();

        return response()->success(ShowVendorData::from(
            [
                ...$vendor->toArray(),
                'media' => $media,
            ]
        ));
    }

    /**
     * Remove the specified vendor from database.
     *
     * @response 204
     *
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function destroy(Vendor $vendor, DeleteVendorAction $action): JsonResponse|ApiResponseInterface
    {
        Gate::authorize('delete', $vendor);
        try {
            $action->handle($vendor);
        } catch (ModelHasRelationshipDataException $exception) {
            return response()->validationError(message: $exception->getMessage());
        }

        return response()->noContentJson();
    }
}
