<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Review;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Review\ReviewData;
use App\Data\Admin\Review\ReviewListItemData;
use App\Events\ReviewableAggregatesChanged;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Reviews
 *
 * @authenticated
 *
 * APIs for managing reviews
 */
final class ReviewController extends Controller
{
    /**
     * Display a listing of the reviews.
     *
     * @responseFile 200  resources/responses/admin/review/index.json
     */
    public function index(Request $request): ApiResponseInterface
    {
        Gate::authorize('view-any', Review::class);
        $reviews = QueryBuilder::for(Review::class)
            ->allowedFilters(
                [
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::exact('reviewable_type'),
                    AllowedFilter::exact('status'),
                    AllowedFilter::exact('is_featured'),
                    AllowedFilter::callback('customer_name', function ($query, $value): void {
                        $query->withWhereHas('user', function ($query) use ($value): void {
                            $query->whereLike(DB::raw("CONCAT(first_name, ' ', last_name)"), "%{$value}%");
                        });
                    }),
                ]
            )
            ->allowedSorts([
                'reviewable_type',
                'rating',
                'status',
                'is_featured',
                'created_at',
                'updated_at',
            ])
            ->when(! $request->has('customer_name'), function ($query): void {
                $query->with('user');
            })
            ->with(['reviewable'])
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->success(ReviewListItemData::collect($reviews));
    }

    /**
     * Display the specified review.
     *
     * @responseFile 200  resources/responses/admin/review/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     */
    public function show(Review $review): ApiResponseInterface
    {
        Gate::authorize('view', $review);
        $review->load(['user', 'reviewable']);

        return response()->success(ReviewData::from($review));
    }

    /**
     * Remove the specified review from storage.
     *
     * @response 204
     *
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     */
    public function destroy(Review $review): JsonResponse
    {
        Gate::authorize('delete', $review);
        $review->delete();
        ReviewableAggregatesChanged::dispatch($review->reviewable_id, $review->reviewable_type);

        return response()->noContentJson();
    }
}
