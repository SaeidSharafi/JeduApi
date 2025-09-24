<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Review;

use App\Actions\Admin\Review\UpdateReviewStatusAction;
use App\Enums\ReviewStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Reviews
 *
 * @authenticated
 * APIs for managing reviews
 */
final class RejectReviewController extends Controller
{
    /**
     * Reject the specified review.
     *
     *
     * @response 200 {
     *     message: "Review rejected successfully.",
     *     "data": null,
     *    "metadata": []
     * }
     */
    public function __invoke(Review $review, UpdateReviewStatusAction $action)
    {
        Gate::authorize('update', $review);
        $action->handle($review, ReviewStatusEnum::REJECTED);

        return response()->success(message: __('messages.review.rejected'));
    }
}
