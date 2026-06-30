<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Review;

use App\Actions\Admin\Review\UpdateReviewStatusAction;
use App\Enums\Content\ReviewStatusEnum;
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
     * @responseFile 200 resources/responses/admin/review/reject.json
     * @responseFile 403 resources/responses/403.json
     */
    public function __invoke(Review $review, UpdateReviewStatusAction $action)
    {
        Gate::authorize('update', $review);
        $action->handle($review, ReviewStatusEnum::REJECTED);

        return apiResponse()->success(message: __('messages.review.rejected'));
    }
}
