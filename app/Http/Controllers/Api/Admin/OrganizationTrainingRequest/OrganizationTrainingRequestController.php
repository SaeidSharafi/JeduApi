<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\OrganizationTrainingRequest;

use App\Actions\Admin\InboundRequest\UpdateInboundRequestAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ContactRequest\ContactRequestAssignmentData;
use App\Data\Admin\ContactRequest\ContactRequestStatusData;
use App\Data\Admin\OrganizationTrainingRequest\OrganizationTrainingRequestData;
use App\Data\Admin\OrganizationTrainingRequest\OrganizationTrainingRequestListItemData;
use App\Data\Admin\OrganizationTrainingRequest\OrganizationTrainingRequestListQueryData;
use App\Http\Controllers\Controller;
use App\Models\OrganizationTrainingRequest;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Organization Training Requests
 *
 * @authenticated
 */
final class OrganizationTrainingRequestController extends Controller
{
    /**
     * List Organization Training Requests.
     *
     * @responseFile 200 resources/responses/admin/organization-training-request/index.json
     */
    public function index(OrganizationTrainingRequestListQueryData $query): ApiResponseInterface
    {
        Gate::authorize('viewAny', OrganizationTrainingRequest::class);

        $requests = QueryBuilder::for(OrganizationTrainingRequest::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('assigned_to_id'),
                AllowedFilter::partial('organization_name'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $query) use ($value): void {
                        $term = '%'.$value.'%';
                        $query->whereLike('first_name', $term)
                            ->orWhereLike('last_name', $term)
                            ->orWhereLike('phone', $term)
                            ->orWhereLike('position', $term)
                            ->orWhereLike('organization_name', $term);
                    });
                }),
            ])
            ->allowedSorts(['status', 'created_at', 'assigned_to_id'])
            ->defaultSort('-created_at')
            ->with(['assignee', 'media'])
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return apiResponse()->success(OrganizationTrainingRequestListItemData::collect($requests));
    }

    /**
     * Show an Organization Training Request.
     *
     * @responseFile 200 resources/responses/admin/organization-training-request/show.json
     */
    public function show(OrganizationTrainingRequest $organizationTrainingRequest): ApiResponseInterface
    {
        Gate::authorize('view', $organizationTrainingRequest);

        return apiResponse()->success(
            OrganizationTrainingRequestData::fromModel($organizationTrainingRequest->load('assignee')),
        );
    }

    /**
     * Update an Organization Training Request status.
     *
     * @responseFile 200 resources/responses/admin/organization-training-request/update.json
     */
    public function status(
        ContactRequestStatusData $data,
        OrganizationTrainingRequest $organizationTrainingRequest,
        UpdateInboundRequestAction $action,
    ): ApiResponseInterface {
        Gate::authorize('update', $organizationTrainingRequest);

        $action->handle($organizationTrainingRequest, ['status' => $data->status]);

        return apiResponse()->updated(
            model: OrganizationTrainingRequest::class,
        );
    }

    /**
     * Assign or unassign an Organization Training Request.
     *
     * @responseFile 200 resources/responses/admin/organization-training-request/update.json
     */
    public function assignment(
        ContactRequestAssignmentData $data,
        OrganizationTrainingRequest $organizationTrainingRequest,
        UpdateInboundRequestAction $action,
    ): ApiResponseInterface {
        $assignee = $data->staff_id ? Staff::query()->findOrFail($data->staff_id) : null;
        Gate::authorize('assign', [$organizationTrainingRequest, $assignee]);

        $action->handle(
            $organizationTrainingRequest,
            ['assigned_to_id' => $data->staff_id],
            auth('staff')->user(),
        );

        return apiResponse()->updated(
            model: OrganizationTrainingRequest::class,
        );
    }
}
