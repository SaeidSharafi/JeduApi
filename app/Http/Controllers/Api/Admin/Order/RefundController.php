<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Refund\CreateRefundAction;
use App\Actions\Admin\Refund\DeletePendingRefundAction;
use App\Actions\Admin\Refund\UpdateRefundAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Refund\RefundCreateData;
use App\Data\Admin\Refund\RefundData;
use App\Data\Admin\Refund\RefundUpdateData;
use App\Exceptions\RefundValidationException;
use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Refunds
 *
 * APIs for managing refunds in the admin panel.
 */
final class RefundController extends Controller
{
    /**
     * Display a listing of the refunds.
     *
     * @queryParam filter[status] string Filter by Refund status. Example: completed
     * @queryParam filter[customer_first_name] string Filter by customer's first name. Example: John
     * @queryParam filter[customer_last_name] string Filter by customer's last name. Example: Doe
     * @queryParam filter[customer_email] string Filter by customer's email. Example: John@example.com
     * @queryParam filter[customer_phone] string Filter by customer's phone number. Example: +1234567890
     * @queryParam filter[increment_id] string Filter by order increment ID. Example: 1001
     * @queryParam sort string Sort by a field. Allowed values: created_at. Prefix with '-' for descending order (e.g.,
     * -created_at).
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 resources/responses/admin/refund/index.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Refund::class);
        $refunds = QueryBuilder::for(Refund::class)
            ->allowedFilters([
                AllowedFilter::partial('customer_first_name', 'order.customer_first_name'),
                AllowedFilter::partial('customer_last_name', 'order.customer_last_name'),
                AllowedFilter::partial('customer_email', 'order.customer_email'),
                AllowedFilter::partial('customer_phone', 'order.customer_phone'),
                AllowedFilter::partial('increment_id', 'order.increment_id'),
                AllowedFilter::exact('status', 'status'),
                AllowedFilter::exact('customer_id', 'customer_id'),
            ])
            ->allowedSorts(['created_at', 'status'])
            ->defaultSort('-created_at')
            ->with('order')
            ->paginate(request()->integer('per_page', config('app.page_size')));

        return apiResponse()->success(RefundData::collect($refunds));
    }

    /**
     * Store a newly created refund.
     *
     * @responseFile 201 resources/responses/admin/refund/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/admin/refund/store-422.json
     */
    public function store(RefundCreateData $data, CreateRefundAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Refund::class);
        try {
            $refund = $action->handle($data);
            $refund->loadMissing('order');
        } catch (RefundValidationException $exception) {
            throw ValidationException::withMessages([$exception->getMessage()]);
        }

        return apiResponse()->created(RefundData::from($refund));
    }

    /**
     * Display the specified refund.
     *
     * @responseFile 200 resources/responses/admin/refund/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(Refund $refund)
    {
        Gate::authorize('view', $refund);
        $refund->load('order');

        return apiResponse()->success(RefundData::from($refund));
    }

    /**
     * Update the specified refund.
     *
     * @responseFile 200 resources/responses/admin/refund/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/admin/refund/update-422.json
     */
    public function update(RefundUpdateData $data, Refund $refund, UpdateRefundAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $refund);
        try {
            $updatedRefund = $action->handle($refund, $data);
            $updatedRefund->loadMissing('order');
        } catch (RefundValidationException $exception) {
            throw ValidationException::withMessages([$exception->getMessage()]);
        }

        return apiResponse()->success(RefundData::from($updatedRefund));
    }

    /**
     * Remove the specified refund.
     *
     * @response 204
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/admin/refund/delete-422.json
     */
    public function destroy(Refund $refund, DeletePendingRefundAction $action): JsonResponse
    {
        Gate::authorize('delete', $refund);
        $action->handle($refund);

        return apiResponse()->noContentJson();
    }
}
