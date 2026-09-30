<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\LearningPath\CreateLearningPathAction;
use App\Actions\Admin\LearningPath\DeleteLearningPathAction;
use App\Actions\Admin\LearningPath\UpdateLearningPathAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\LearningPath\LearningPathCreateData;
use App\Data\Admin\LearningPath\LearningPathData;
use App\Data\Admin\LearningPath\LearningPathListItemData;
use App\Data\Admin\LearningPath\LearningPathUpdateData;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Learning Path Management
 *
 * @authenticated Staff
 */
final class LearningPathController extends Controller
{
    /**
     * List learning path drafts and other staff-visible lifecycle states.
     *
     * @queryParam filter[title] string Filter by title. Example: Backend
     * @queryParam filter[slug] string Filter by slug. Example: backend-engineering-path
     * @queryParam filter[status] string Filter by lifecycle status. Example: draft
     * @queryParam sort string Sort by display_order, title, status, created_at, or updated_at.
     *     Prefix with '-' for descending order. Example: -display_order
     * @queryParam page integer Page number. Example: 2
     * @queryParam per_page integer Results per page. Example: 15
     *
     * @responseFile 200 resources/responses/admin/learning-path/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', LearningPath::class);

        $learningPaths = QueryBuilder::for(LearningPath::class)
            ->allowedFilters(['title', 'slug', 'status'])
            ->allowedSorts(['display_order', 'title', 'slug', 'status', 'created_at', 'updated_at'])
            ->defaultSort('-updated_at')
            ->orderByDesc('id')
            ->withCount('steps')
            ->paginate(request()->integer('per_page', config('app.page_size')))
            ->withQueryString();

        return apiResponse()->success(LearningPathListItemData::collect($learningPaths));
    }

    /**
     * Create a learning path draft or a valid non-empty published path.
     *
     * @responseFile 201 resources/responses/admin/learning-path/show.json
     * @responseFile 422 resources/responses/422.json
     */
    public function store(LearningPathCreateData $data, CreateLearningPathAction $action): ApiResponseInterface
    {
        Gate::authorize('create', LearningPath::class);
        $learningPath = $action->handle($data);

        return apiResponse()->created(LearningPathData::fromModel($learningPath), model: LearningPath::class);
    }

    /**
     * Display a learning path.
     *
     * @responseFile 200 resources/responses/admin/learning-path/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function show(LearningPath $learningPath): ApiResponseInterface
    {
        Gate::authorize('view', $learningPath);

        return apiResponse()->success(LearningPathData::fromModel($learningPath));
    }

    /**
     * Replace a learning path, including content for an already-published path.
     *
     * @responseFile 200 resources/responses/admin/learning-path/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function update(
        LearningPathUpdateData $data,
        LearningPath $learningPath,
        UpdateLearningPathAction $action,
    ): ApiResponseInterface {
        Gate::authorize('update', $learningPath);
        $updatedLearningPath = $action->handle($data, $learningPath);

        return apiResponse()->updated(
            LearningPathData::fromModel($updatedLearningPath),
            model: LearningPath::class,
        );
    }

    /**
     * Delete a never-published learning path draft. Published and archived paths
     * must be retained and are not deletable.
     *
     * @response 204
     *
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function destroy(
        LearningPath $learningPath,
        DeleteLearningPathAction $action,
    ): JsonResponse {
        Gate::authorize('delete', $learningPath);
        $action->handle($learningPath);

        return apiResponse()->noContentJson();
    }
}
