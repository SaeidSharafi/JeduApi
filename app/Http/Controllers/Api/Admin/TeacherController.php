<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Teacher\CreateTeacherAction;
use App\Actions\Teacher\DeleteTeacherAction;
use App\Actions\Teacher\UpdateTeacherAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\MediaData;
use App\Data\Admin\Teacher\CreateTeacherData;
use App\Data\Admin\Teacher\ShowTeacherData;
use App\Data\Admin\Teacher\TeacherListItemData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Teacher
 *
 * APIs for managing teachers.
 *
 * @authenticated Staff
 */
final class TeacherController extends Controller
{
    /**
     * Display a listing of the teacher.
     *
     * This endpoint returns a paginated list of teachers with optional filters and sorting.
     *
     * @queryParam filter[first_name] string Filter by teacher's first name. Example: John
     * @queryParam filter[last_name] string Filter by teacher's last name. Example: Doe
     * @queryParam filter[email] string Filter by teacher's email. Example: teahcer@example.com
     * @queryParam filter[phone] string Filter by teacher's email. Example: 09315468795
     * @queryParam sort string Sort by a field. Allowed values: first_name, last_name, email, phone.
     *                      Prefix with '-' for descending order (e.g., -last_name for descending by last name).
     *                      Example: last_name
     * @queryParam page integer Page number for pagination. Example: 2
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Teacher::class);
        $teachers = QueryBuilder::for(Teacher::class)
            ->allowedFilters(['first_name', 'last_name', 'email', 'phone'])
            ->allowedSorts(['first_name', 'last_name', 'email', 'phone'])
            ->with('user')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(TeacherListItemData::collect($teachers));
    }

    /**
     * Store a newly created teacher in database.
     *
     * @response 201
     *
     * @responseFile 422 responses/422.json
     */
    public function store(CreateTeacherData $data, CreateTeacherAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Teacher::class);
        $action->handle($data);

        return response()->created(model: Teacher::class);
    }

    /**
     * Display the specified teacher.
     *
     * @responseFile 200 responses/teacher/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(Teacher $teacher): ApiResponseInterface
    {
        Gate::authorize('view', $teacher);
        $teacher->load('user');
        $media = $teacher->getAllMediaByTag()
            ->map(function ($item, $tag) {
                return $item->map(function ($mediaItem) use ($tag) {
                    return MediaData::fromModel($mediaItem, $tag);
                });
            })->toArray();

        return response()->success(ShowTeacherData::from([
            ...$teacher->toArray(),
            'user'  => $teacher->user,
            'media' => $media,
        ]));
    }

    /**
     * Update the specified teacher in database.
     *
     * @responseFile 200 responses/teacher/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function update(CreateTeacherData $data, Teacher $teacher, UpdateTeacherAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $teacher);
        $action->handle($data, $teacher);

        return response()->updated(model: Teacher::class);
    }

    /**
     * Remove the specified teacher from database.
     *
     * @response 204
     * @responseFile  244 responses/422-delete.json
     */
    public function destroy(Teacher $teacher, DeleteTeacherAction $action): JsonResponse|ApiResponseInterface
    {
        Gate::authorize('delete', $teacher);
        try {
            $action->handle($teacher);
        } catch (ModelHasRelationshipDataException $exception) {
            return response()->validationError(
                message: __(
                    'messages.errors.model_has_relationship_data',
                    [
                        'model'         => __('messages.models.user'),
                        'related_model' => getModelLabel($exception->getRelatedModel()),
                    ]
                )
            );
        }

        return response()->noContentJson();
    }
}
