<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\Student\SubmitReviewAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\Review\ReviewData;
use App\Data\Shop\Student\Review\SubmitReviewData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;

/**
 * @group Shop - Student - Reviews
 *
 * @authenticated user
 */
final class SubmitReviewController extends Controller
{
    /**
     * Submit a review and rating for an enrollment.
     *
     * Creates a pending review for the productable behind the given enrollment. The review is
     * invisible to the public catalog until staff approve it. Only the enrollment owner may
     * submit, and only while the enrollment is active.
     *
     * @responseFile 201 resources/responses/shop/reviews/show.json
     *
     * @response 404 {"message": "Enrollment not found."}
     * @response 422 {"message": "Validation error.", "errors": {"review": ["You have already reviewed this course."]}, "metadata": []}
     */
    public function __invoke(Enrollment $enrollment, SubmitReviewData $data, SubmitReviewAction $action): ApiResponseInterface
    {
        if (auth()->user()->id !== $enrollment->customer_id) {
            return apiResponse()->notFound(__('messages.enrollments.not_found'));
        }

        $review = $action->handle($data, $enrollment, auth()->user());

        return apiResponse()->created(ReviewData::fromModel($review), __('messages.review.submitted'));
    }
}
