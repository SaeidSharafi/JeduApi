<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Actions\Admin\Course\CreateCourseAction;
use App\Actions\Admin\Course\DeleteCourseAction;
use App\Actions\Admin\Course\UpdateCourseAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Course\CourseListItemData;
use App\Data\Admin\Course\CreateCourseData;
use App\Data\Admin\Course\ShowCourseData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Course Management
 *
 * APIs for managing courses
 *
 * @authenticated Staff
 */
final class CourseController extends Controller
{
    /**
     * return a list of the courses.
     *
     * @queryParam filter[slug] string Filter by course slug. Example: math-101
     * @queryParam filter[full_name] string Filter by course name. Example: Mathematics
     * @queryParam filter[short_name] string Filter by course short name. Example: MATH
     * @queryParam filter[status] string Filter by course status. Example: active
     * @queryParam sort string Sort by a field. Allowed values: slug, name, short_name, status. Prefix with '-' for
     *     descending order (e.g., -name for descending by name). Example: name
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/course/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Course::class);
        $courses = QueryBuilder::for(Course::class)
            ->allowedFilters(['slug', 'full_name', 'short_name', 'status'])
            ->allowedSorts(['slug', 'full_name', 'short_name', 'status'])
            ->with('categories', 'digitalAssets')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return Response::success(data: CourseListItemData::collect($courses)->toArray());
    }

    /**
     * Create a new course.
     *
     * @responseFile 201 responses/201.json
     */
    public function store(CreateCourseData $data, CreateCourseAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Course::class);

        $action->handle($data);

        return response()->created();
    }

    /**
     *  return the specified course detail.
     *
     * @responseFile 200 responses/course/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function show(Course $course): ApiResponseInterface
    {
        Gate::authorize('view', $course);
        $course->load('categories', 'digitalAssets')
            ->loadMediaWithVariantsMatchAll();

        $media = $course->getAllMedia();

        return response()->success(ShowCourseData::from([
            ...$course->toArray(),
            'categories' => $course->categories,
            'media'      => $media,
        ]));
    }

    /**
     * Update the specified course.
     *
     * @responseFile 200 responses/course/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function update(CreateCourseData $data, Course $course, UpdateCourseAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $course);
        $action->handle($data, $course);
        $course
            ->load('categories', 'digitalAssets')
            ->loadMediaWithVariantsMatchAll();
        $media = $course->getAllMedia();

        return response()->success(ShowCourseData::from([
            ...$course->toArray(),
            'categories' => $course->categories,
            'media'      => $media,
        ])->toArray());
    }

    /**
     * Remove the specified course
     *
     * @response 204
     *
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     *
     * @throws ModelHasRelationshipDataException
     */
    public function destroy(Course $course, DeleteCourseAction $action): JsonResponse|ApiResponseInterface
    {
        Gate::authorize('delete', $course);
        try {
            $action->handle($course);
        } catch (ModelHasRelationshipDataException $exception) {
            return response()->validationError(message: $exception->getMessage());
        }

        return response()->noContentJson();
    }
}
