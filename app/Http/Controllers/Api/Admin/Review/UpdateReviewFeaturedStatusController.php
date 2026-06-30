<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Review;

use App\Actions\Admin\Review\UpdateReviewFeaturedStatusAction;
use App\Data\Admin\Review\FeaturedStatusData;
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
final class UpdateReviewFeaturedStatusController extends Controller
{
    /**
     * Update the featured status of the specified review.
     *
     * @responseFile 200 resources/responses/admin/review/update-featured-status.json
     * @responseFile 403 resources/responses/403.json
     */
    public function __invoke(FeaturedStatusData $data, Review $review, UpdateReviewFeaturedStatusAction $action)
    {
        Gate::authorize('update-featured-status', $review);
        $action->handle($review, $data);

        return apiResponse()->updated(model: Review::class);
    }
}
