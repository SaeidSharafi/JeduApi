<?php

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\MyCourses\EnrolmentData;
use App\Http\Controllers\Controller;
use App\Models\Enrolment;
use Illuminate\Http\Request;

/**
 * @group Shop - Student Dash - My Courses
 *
 * @authenticated user
 *
 */
class EnrolmentController extends Controller
{
    /**
     * @queryParam filter[fulfillment_type] string Filter by fulfillment type. Example: digital
     * @queryParam filter[name] string Filter by product name. Example: Course Name
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/shop/enrolments/index.json
     *
     */
    public function index(): ApiResponseInterface
    {
        $filters = request()->array('filter', []);
        $enrolments = auth()->user()->enrolments()
            ->withWhereHas(
                'productDeliveryOption' , function ($query) use ($filters) {
                    $query->when(data_get($filters, 'fulfillment_type'), function ($query, $fulfillmentType) {
                        $query->where('fulfillment_type', $fulfillmentType);
                    })->withWhereHas(
                        'product' , function ($query) use ($filters) {
                            $query
                                ->when(data_get($filters, 'name'), function ($query, $name) {
                                    $query->where('name', 'like', "%{$name}%");
                                })
                                ->with(['productableWithAllRelations']);
                        })->with('teachers.media');
                }
            )
            ->with(['orderItem.vendor'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();
        return response()->success(EnrolmentData::collect($enrolments));
    }

    /**
     * Show a specific enrolment.
     *
     * @responseFile 200 responses/shop/enrolments/show.json
     * @response 404 {"message": "Enrolment not found."}
     */
    public function show(Enrolment $enrolment): ApiResponseInterface
    {
        if (auth()->user()->id !== $enrolment->customer_id) {
            return response()->notFound(__('messages.enrollments.not_found'));
        }
        $enrolment->loadMissing([
            'productDeliveryOption.product.productableWithAllRelations',
            'productDeliveryOption.teachers.media',
            'orderItem.vendor',
        ]);
        return response()->success(EnrolmentData::from($enrolment));
    }
}
