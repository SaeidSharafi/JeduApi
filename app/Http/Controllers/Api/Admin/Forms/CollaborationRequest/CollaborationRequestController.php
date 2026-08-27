<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Forms\CollaborationRequest;

use App\Actions\Admin\InboundRequest\UpdateInboundRequestAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\CollaborationRequest\CollaborationRequestData;
use App\Data\Admin\CollaborationRequest\CollaborationRequestListItemData;
use App\Data\Admin\CollaborationRequest\CollaborationRequestListQueryData;
use App\Data\Admin\ContactRequest\ContactRequestAssignmentData;
use App\Data\Admin\ContactRequest\ContactRequestNoteData;
use App\Data\Admin\ContactRequest\ContactRequestStatusData;
use App\Http\Controllers\Controller;
use App\Models\CollaborationRequest;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Collaboration Requests
 *
 * @authenticated
 */
final class CollaborationRequestController extends Controller
{
    /**
     * List Collaboration Requests.
     *
     * @responseFile 200 resources/responses/admin/collaboration-request/index.json
     */
    public function index(CollaborationRequestListQueryData $query): ApiResponseInterface
    {
        Gate::authorize('viewAny', CollaborationRequest::class);
        $requests = QueryBuilder::for(CollaborationRequest::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('assigned_to_id'),
                AllowedFilter::partial('department'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(fn (Builder $query): Builder => $query->whereLike('full_name', '%'.$value.'%')->orWhereLike('phone', '%'.$value.'%')->orWhereLike('email', '%'.$value.'%')->orWhereLike('department', '%'.$value.'%'));
                }),
            ])
            ->allowedSorts(['status', 'created_at', 'assigned_to_id'])
            ->defaultSort('-created_at')
            ->with(['assignee', 'media'])
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return apiResponse()->success(CollaborationRequestListItemData::collect($requests));
    }

    /** @responseFile 200 resources/responses/admin/collaboration-request/show.json */
    public function show(CollaborationRequest $collaborationRequest): ApiResponseInterface
    {
        Gate::authorize('view', $collaborationRequest);

        return apiResponse()->success(CollaborationRequestData::fromModel($collaborationRequest->load(['assignee', 'media'])));
    }

    /** @responseFile 200 resources/responses/admin/collaboration-request/show.json */
    public function status(ContactRequestStatusData $data, CollaborationRequest $collaborationRequest, UpdateInboundRequestAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $collaborationRequest);

        return apiResponse()->updated(CollaborationRequestData::fromModel($action->handle($collaborationRequest, ['status' => $data->status])), model: CollaborationRequest::class);
    }

    /** @responseFile 200 resources/responses/admin/collaboration-request/show.json */
    public function assignment(ContactRequestAssignmentData $data, CollaborationRequest $collaborationRequest, UpdateInboundRequestAction $action): ApiResponseInterface
    {
        $assignee = $data->staff_id ? Staff::query()->findOrFail($data->staff_id) : null;
        Gate::authorize('assign', [$collaborationRequest, $assignee]);

        return apiResponse()->updated(CollaborationRequestData::fromModel($action->handle($collaborationRequest, ['assigned_to_id' => $data->staff_id], auth('staff')->user())), model: CollaborationRequest::class);
    }

    /** @responseFile 200 resources/responses/admin/collaboration-request/show.json */
    public function note(ContactRequestNoteData $data, CollaborationRequest $collaborationRequest, UpdateInboundRequestAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $collaborationRequest);

        return apiResponse()->updated(CollaborationRequestData::fromModel($action->handle($collaborationRequest, ['note' => $data->note])), model: CollaborationRequest::class);
    }
}
