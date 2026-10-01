<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\LearningPath;

use App\Actions\Admin\LearningPath\ArchiveLearningPathAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\LearningPath\LearningPathData;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Learning Path Management
 *
 * @authenticated Staff
 *
 * This operation is intentionally separate from replacement so a published
 * path cannot be archived accidentally through the normal update endpoint.
 */
final class ArchiveLearningPathController extends Controller
{
    /**
     * Archive a published learning path.
     *
     * Archived paths remain visible to authorized staff, retain their steps and
     * media, and are excluded from public discovery. Only a published path can
     * be archived.
     *
     * @responseFile 200 resources/responses/admin/learning-path/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        LearningPath $learningPath,
        ArchiveLearningPathAction $action,
    ): ApiResponseInterface {
        Gate::authorize('update', $learningPath);
        $archivedLearningPath = $action->handle($learningPath);

        return apiResponse()->updated(
            LearningPathData::fromModel($archivedLearningPath),
            model: LearningPath::class,
        );
    }
}
