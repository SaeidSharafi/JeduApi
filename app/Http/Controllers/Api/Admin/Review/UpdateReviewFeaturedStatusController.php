<?php

namespace App\Http\Controllers\Api\Admin\Review;

use App\Actions\Admin\Review\UpdateReviewFeaturedStatusAction;
use App\Actions\Admin\Review\UpdateReviewStatusAction;
use App\Data\Admin\Review\FeaturedStatusData;
use App\Enums\ReviewStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Reviews
 *
 * @authenticated
 *
 * APIs for managing reviews
 */
class UpdateReviewFeaturedStatusController extends Controller
{
    /**
     *
     * Update the featured status of the specified review.
     *
     * @bodyParam is_featured boolean optional The featured status to set. If not provided, the status will be toggled.
     * @response 200 {
     *     message: "Review updated successfully.",
     *     "data": null,
     *    "metadata": {}
     * }
     */
    public function __invoke(FeaturedStatusData $data, Review $review, UpdateReviewFeaturedStatusAction $action)
    {
        Gate::authorize('update-featured-status', $review);
        $action->handle($review, $data);
        return response()->updated(model: Review::class);
    }
}
