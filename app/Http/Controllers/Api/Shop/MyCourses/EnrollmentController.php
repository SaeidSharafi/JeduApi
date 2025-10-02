<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\MyCourses\EnrollmentData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;

/**
 * @group Shop - Student Dash - My Courses
 *
 * @authenticated user
 */
final class EnrollmentController extends Controller
{
    /**
     * @queryParam filter[fulfillment_type] string Filter by fulfillment type. Example: digital
     * @queryParam filter[name] string Filter by product name. Example: Course Name
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/shop/enrollments/index.json
     */
    public function index(): ApiResponseInterface
    {
        $filters     = request()->array('filter', []);
        $enrollments = auth()->user()->enrollments()
            ->withWhereHas(
                'productDeliveryOption', function ($query) use ($filters): void {
                    $query->when(data_get($filters, 'fulfillment_type'), function ($query, $fulfillmentType): void {
                        $query->where('fulfillment_type', $fulfillmentType);
                    })->withWhereHas(
                        'product', function ($query) use ($filters): void {
                            $query
                                ->when(data_get($filters, 'name'), function ($query, $name): void {
                                    $query->whereLike('name', "%{$name}%");
                                })
                                ->with(['productableWithAllRelations']);
                        })->with('teachers.media');
                }
            )
            ->with(['orderItem.vendor'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(EnrollmentData::collect($enrollments));
    }

    /**
     * Show a specific enrollment.
     *
     * @responseFile 200 responses/shop/enrollments/show.json
     *
     * @response 404 {"message": "Enrollment not found."}
     */
    public function show(Enrollment $enrollment): ApiResponseInterface
    {
        if (auth()->user()->id !== $enrollment->customer_id) {
            return response()->notFound(__('messages.enrollments.not_found'));
        }
        $enrollment->loadMissing([
            'productDeliveryOption.product.productableWithAllRelations',
            'productDeliveryOption.teachers.media',
            'orderItem.vendor',
        ]);

        return response()->success(EnrollmentData::from($enrollment));
    }
}
