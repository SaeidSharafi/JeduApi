<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Actions\Admin\Enrollment\DeleteEnrollmentAction;
use App\Actions\Admin\Enrollment\UpdateEnrollmentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\EnrollmentData;
use App\Data\Admin\Enrollment\EnrollmentListItemData;
use App\Data\Admin\Enrollment\EnrollmentUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Enrollment Management
 *
 * @subgroup Enrollments
 *
 * APIs for managing enrollments in the admin panel.
 *
 * @authenticated
 */
final class EnrollmentController extends Controller
{
    /**
     * Display a listing of the enrollments.
     *
     * @queryParam filter[enrollment_status] string Filter by enrollment status. Example: active
     * @queryParam filter[customer_id] string Filter by customer ID. Example: 1
     * @queryParam filter[order_id] string Filter by order ID. Example: 1
     * @queryParam filter[product_delivery_option_id] string Filter by product delivery option ID. Example: 1
     * @queryParam sort string Sort by a field. Allowed values: created_at, enrollment_status, access_start_date. Prefix with '-' for descending order (e.g., -created_at).
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile storage/responses/admin/enrollment/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Enrollment::class);
        $enrollments = QueryBuilder::for(Enrollment::class)
            ->allowedFilters([
                'enrollment_status',
                'customer_id',
                'order_id',
                'product_delivery_option_id',
            ])
            ->allowedSorts(['created_at', 'enrollment_status', 'access_start_date'])
            ->defaultSort('-created_at')
            ->with([
                'order.items.vendor',
                'order.payments',
                'customer',
                'productDeliveryOption',
                'orderItem.vendor',
            ])
            ->paginate(request()->integer('per_page', 15));

        return response()->success(EnrollmentListItemData::collect($enrollments));
    }

    /**
     * Display the specified enrollment.
     *
     * @responseFile storage/responses/admin/enrollment/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function show(Enrollment $enrollment): ApiResponseInterface
    {
        Gate::authorize('view', $enrollment);
        $enrollment->load([
            'order.items.vendor',
            'order.payments',
            'customer',
            'productDeliveryOption',
            'orderItem.vendor',
        ]);

        return response()->success(EnrollmentData::from($enrollment));
    }

    /**
     * Update the specified enrollment.
     *
     * @responseFile storage/responses/admin/enrollment/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     * @responseFile 403 responses/403.json
     */
    public function update(EnrollmentUpdateData $data, Enrollment $enrollment, UpdateEnrollmentAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $enrollment);
        $enrollment = $action->handle($enrollment, $data);
        $enrollment->load([
            'order.items.vendor',
            'order.payments',
            'customer',
            'productDeliveryOption',
            'orderItem.vendor',
        ]);

        return response()->success(EnrollmentData::from($enrollment));
    }

    /**
     * Remove the specified enrollment.
     *
     * @response 204
     *
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function destroy(Enrollment $enrollment, DeleteEnrollmentAction $action): JsonResponse
    {
        Gate::authorize('delete', $enrollment);
        $action->handle($enrollment);

        return response()->noContentJson();
    }
}
