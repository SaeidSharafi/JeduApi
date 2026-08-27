<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Forms\ContactRequest;

use App\Actions\Admin\InboundRequest\UpdateInboundRequestAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\ContactRequest\ContactRequestAssignmentData;
use App\Data\Admin\ContactRequest\ContactRequestData;
use App\Data\Admin\ContactRequest\ContactRequestListItemData;
use App\Data\Admin\ContactRequest\ContactRequestListQueryData;
use App\Data\Admin\ContactRequest\ContactRequestNoteData;
use App\Data\Admin\ContactRequest\ContactRequestStatusData;
use App\Http\Controllers\Controller;
use App\Models\ContactUsRequest;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Contact Requests
 *
 * @authenticated
 */
final class ContactRequestController extends Controller
{
    /**
     * List Contact Requests.
     *
     * @responseFile 200 resources/responses/admin/contact-request/index.json
     */
    public function index(ContactRequestListQueryData $query): ApiResponseInterface
    {
        Gate::authorize('viewAny', ContactUsRequest::class);
        $requests = QueryBuilder::for(ContactUsRequest::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('assigned_to_id'),
                AllowedFilter::partial('subject'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $query) use ($value): void {
                        $query->whereLike('full_name', '%'.$value.'%')->orWhereLike('phone', '%'.$value.'%')->orWhereLike('email', '%'.$value.'%')->orWhereLike('subject', '%'.$value.'%');
                    });
                }),
            ])
            ->allowedSorts(['status', 'created_at', 'assigned_to_id'])
            ->defaultSort('-created_at')
            ->with('assignee')
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return apiResponse()->success(ContactRequestListItemData::collect($requests));
    }

    /**
     * View a Contact Request.
     *
     * @responseFile 200 resources/responses/admin/contact-request/show.json
     */
    public function show(ContactUsRequest $contactRequest): ApiResponseInterface
    {
        Gate::authorize('view', $contactRequest);

        return apiResponse()->success(ContactRequestData::from($contactRequest->load('assignee')));
    }

    /**
     * Update a Contact Request status.
     *
     * @responseFile 200 resources/responses/admin/contact-request/show.json
     */
    public function status(ContactRequestStatusData $data, ContactUsRequest $contactRequest, UpdateInboundRequestAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $contactRequest);

        return apiResponse()->updated(ContactRequestData::from($action->handle($contactRequest, ['status' => $data->status])), model: ContactUsRequest::class);
    }

    /**
     * Assign or unassign a Contact Request.
     *
     * @responseFile 200 resources/responses/admin/contact-request/show.json
     */
    public function assignment(ContactRequestAssignmentData $data, ContactUsRequest $contactRequest, UpdateInboundRequestAction $action): ApiResponseInterface
    {
        $assignee = $data->staff_id ? Staff::query()->findOrFail($data->staff_id) : null;
        Gate::authorize('assign', [$contactRequest, $assignee]);

        return apiResponse()->updated(ContactRequestData::from($action->handle($contactRequest, ['assigned_to_id' => $data->staff_id], auth('staff')->user())), model: ContactUsRequest::class);
    }

    /**
     * Update a Contact Request internal note.
     *
     * @responseFile 200 resources/responses/admin/contact-request/show.json
     */
    public function note(ContactRequestNoteData $data, ContactUsRequest $contactRequest, UpdateInboundRequestAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $contactRequest);

        return apiResponse()->updated(ContactRequestData::from($action->handle($contactRequest, ['note' => $data->note])), model: ContactUsRequest::class);
    }
}
