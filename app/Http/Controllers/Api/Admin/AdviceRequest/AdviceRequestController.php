<?php

namespace App\Http\Controllers\Api\Admin\AdviceRequest;

use App\Actions\Admin\AdviceRequest\UpdateAdviceRequestAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\AdviceRequest\AdviceRequestData;
use App\Data\Admin\AdviceRequest\AdviceRequestUpdateData;
use App\Http\Controllers\Controller;
use App\Models\AdviceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Advice Requests
 * @authenticated
 *
 * APIs for managing advice requests
 */
class AdviceRequestController extends Controller
{
    /**
     * List Advice Requests
     *
     * Display a paginated list of advice requests with filtering and sorting options.
     *
     * @queryParam filter[status] string Filter by status. Example: pending
     * @queryParam filter[handled_by_id] integer Filter by handler ID. Example: 1
     * @queryParam sort string Sort by fields. Prefix with '-' for descending order. Example: -created_at
     * @queryParam per_page integer Number of items per page. Default is 15. Example: 15
     * @queryParam page integer Page number. Default is 1. Example: 1
     *
     * @responseFile 200 responses/advice-request/index.json
     * @response 403 responses/403.json
     *
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', AdviceRequest::class);
        $requests = QueryBuilder::for(AdviceRequest::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('handled_by_id')
            ])
            ->allowedSorts(['status', 'created_at', 'handled_by_id'])
            ->defaultSort('-created_at')
            ->with('handler')
            ->paginate(request()->get('per_page', 15))
            ->withQueryString();
        return response()->success(AdviceRequestData::collect($requests));
    }

    /**
     * View Advice Request
     *
     * Display detailed information about a specific advice request.
     *
     * @responseFile 200 responses/advice-request/show.json
     * @response 403 responses/403.json
     * @response 404 responses/404.json
     */
    public function show(AdviceRequest $adviceRequest): ApiResponseInterface
    {
        Gate::authorize('view', $adviceRequest);
        $adviceRequest->load('handler');
        return response()->success(AdviceRequestData::from($adviceRequest));
    }

    /**
     * Update Advice Request
     *
     * Update the details of a specific advice request.
     *
     * @responseFile 200 responses/advice-request/show.json
     * @response 403 responses/403.json
     * @response 404 responses/404.json
     */
    public function update(
        AdviceRequestUpdateData $data,
        AdviceRequest $adviceRequest,
        UpdateAdviceRequestAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $adviceRequest);

        $adviceRequest = $action->handle($data, $adviceRequest, auth('staff')->user());
        $adviceRequest->load('handler');
        return response()->updated(AdviceRequestData::from($adviceRequest), model: AdviceRequest::class);
    }

    /**
     * Delete Advice Request
     *
     * Remove a specific advice request from the system.
     *
     * @response 204
     * @response 403 responses/403.json
     * @response 404 responses/404.json
     */
    public function destroy(AdviceRequest $adviceRequest): JsonResponse
    {
        Gate::authorize('delete', $adviceRequest);

        $adviceRequest->delete();

        return response()->noContentJson();
    }
}
