<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\Student\GetEnrollmentDetailAction;
use App\Actions\Shop\Student\ListStudentEnrollmentsAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\Enrollment\EnrollmentData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\User;

/**
 * @group Shop - Student - Seminars
 *
 * @authenticated user
 */
final class SeminarController extends Controller
{
    /**
     * Get a paginated list of the authenticated user's seminar enrollments.
     *
     * Only enrollments whose product is of seminar type are returned.
     *
     * @queryParam filter[fulfillment_type] string Filter by fulfillment type. Example: online_service
     * @queryParam filter[name] string Filter by product name. Example: Seminar Name
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 resources/responses/shop/seminars/index.json
     */
    public function index(ListStudentEnrollmentsAction $action): ApiResponseInterface
    {
        /** @var User $user */
        $user = auth()->user();

        $enrollments = $action->handle(
            $user,
            ProductableEnum::SEMINAR,
            request()->array('filter'),
            request()->integer('per_page', config('app.page_size')),
        );

        return apiResponse()->success(EnrollmentData::collect($enrollments));
    }

    /**
     * Show a specific seminar enrollment.
     *
     * Retrieve detailed information, including the enrolled seminar, its teachers and the live-session
     * join access, for a single seminar enrollment owned by the authenticated user.
     *
     * @responseFile 200 resources/responses/shop/seminars/show.json
     *
     * @response 404 {"message": "Enrollment not found."}
     */
    public function show(Enrollment $enrollment, GetEnrollmentDetailAction $action): ApiResponseInterface
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->id !== $enrollment->customer_id
            || ! $enrollment->isOfProductableType(ProductableEnum::SEMINAR)
        ) {
            return apiResponse()->notFound(__('messages.enrollments.not_found'));
        }

        $enrollment->loadMissing([
            'productDeliveryOption.product.productableWithAllRelations',
            'productDeliveryOption.teachers.media',
            'orderItem.vendor',
        ]);

        return apiResponse()->success($action->handle($enrollment));
    }
}
