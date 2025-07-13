<?php

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Data\Shop\MyCourses\EnrolmentData;
use App\Http\Controllers\Controller;
use App\Models\Enrolment;
use Illuminate\Http\Request;

class EnrolmentController extends Controller
{
    public function index()
    {
        $enrolments = auth()->user()->enrolments()
            ->with([
                'productDeliveryOption.product.productableWithAllRelations',
                'productDeliveryOption.teachers.media',
                'orderItem.vendor',
            ])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(EnrolmentData::collect($enrolments));
    }

    public function show(Enrolment $enrolment)
    {
        if (auth()->user()->id !== $enrolment->customer_id) {
            return response()->forbidden();
        }
        $enrolment->loadMissing([
            'productDeliveryOption.product.productableWithAllRelations',
            'productDeliveryOption.teachers.media',
            'orderItem.vendor',
        ]);
        return response()->success(EnrolmentData::from($enrolment));
    }
}
