<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\LearningPath;

use App\Actions\Shop\LearningPath\ListPublishedLearningPathsAction;
use App\Actions\Shop\LearningPath\ShowPublishedLearningPathAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\LearningPath\LearningPathCardData;
use App\Data\Shop\LearningPath\LearningPathDetailData;
use App\Data\Shop\PaginationRequestData;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;

/**
 * @group Shop - Learning Paths
 *
 * @unauthenticated
 *
 * Public, non-commercial Learning Path catalog APIs.
 */
final class LearningPathController extends Controller
{
    /**
     * List published Learning Paths in their public display order.
     *
     * @responseFile 200 resources/responses/shop/learning-paths/index.json
     */
    public function index(
        PaginationRequestData $requestData,
        ListPublishedLearningPathsAction $action,
    ): ApiResponseInterface {
        $learningPaths = $action->handle($requestData)
            ->through(static fn (LearningPath $learningPath): LearningPathCardData => LearningPathCardData::fromModel($learningPath));

        return apiResponse()->success($learningPaths);
    }

    /**
     * Get a published Learning Path by its public slug.
     *
     * @urlParam slug string required The public Learning Path slug. Example: backend-learning-path
     *
     * @responseFile 200 resources/responses/shop/learning-paths/show.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(
        string $slug,
        ShowPublishedLearningPathAction $action,
    ): ApiResponseInterface {
        return apiResponse()->success(LearningPathDetailData::fromModel($action->handle($slug)));
    }
}
