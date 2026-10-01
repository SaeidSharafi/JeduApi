<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\LearningPath;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\LearningPath\LearningPathCardData;
use App\Data\Shop\LearningPath\LearningPathDetailData;
use App\Data\Shop\PaginationRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Services\LearningPath\LearningPathStepResolverService;

/**
 * @group Shop - Learning Paths
 *
 * @unauthenticated
 *
 * Public, non-commercial Learning Path catalog APIs.
 *
 * The shop projection keeps editorial step fields separate from a merged
 * `product` object. That object uses the current sellable Product when one is
 * available and falls back to the referenced productable for identity fields.
 * Pricing is null for a coming-soon step; action state is serialized with its
 * enum value and translated label.
 */
final class LearningPathController extends Controller
{
    /**
     * List published Learning Paths in their public display order.
     *
     * Each card contains `title`, `slug`, `description`, the denormalized
     * `thumbnail_url` (nullable), and `step_count`. Draft and archived paths are not
     * included.
     *
     * @responseFile 200 resources/responses/shop/learning-paths/index.json
     */
    public function index(PaginationRequestData $requestData): ApiResponseInterface
    {
        $learningPaths = LearningPath::query()
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->withProductableMedia()
            ->withCount('steps')
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(
                perPage: $requestData->per_page ?? 15,
                page: $requestData->page,
            )
            ->withQueryString();

        return apiResponse()->success(LearningPathCardData::collect($learningPaths));
    }

    /**
     * Get a published Learning Path by its public slug.
     *
     * Steps are returned in editorial position order. Each step exposes its
     * path-specific title and description, the productable alias and ID, a
     * merged `product` projection, and an `action` describing whether the user
     * can view the current product. Product name, short description, and slug
     * override productable fallbacks when populated; the productable supplies
     * the fallback name, excerpt, slug, and thumbnail. `price` and
     * `price_data` are null when no published visible Product is available.
     *
     * `action.type` is `view_product` for a resolved Product and `coming_soon`
     * otherwise. `action.state` contains `{value, label}` and `action.enabled`
     * is true only when a published delivery option has current capacity.
     *
     * @urlParam slug string required The public Learning Path slug. Example: backend-learning-path
     *
     * @responseFile 200 resources/responses/shop/learning-paths/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(
        string $slug,
        LearningPathStepResolverService $resolver,
    ): ApiResponseInterface {
        $learningPath = LearningPath::query()
            ->where('slug', $slug)
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->withProductableMedia()
            ->with('steps.productable')
            ->firstOrFail();

        return apiResponse()->success(
            LearningPathDetailData::fromModel($learningPath, $resolver->resolve($learningPath)),
        );
    }
}
