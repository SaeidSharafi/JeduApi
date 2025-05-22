<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Course\CreateCourseAction;
use App\Actions\Course\DeleteCourseAction;
use App\Actions\Course\UpdateCourseAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Course\CreateCourseData;
use App\Data\Course\CourseListItemData;
use App\Data\Course\ShowCourseData;
use App\Data\MediaData;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Plank\Mediable\Media;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Course Management
 *
 * APIs for managing courses
 *
 * @authenticated Admin
 */
final class CourseController extends Controller
{
    /**
     * return a list of the courses.
     *
     * @queryParam filter[slug] string Filter by course slug. Example: math-101
     * @queryParam filter[name] string Filter by course name. Example: Mathematics
     * @queryParam filter[short_name] string Filter by course short name. Example: MATH
     * @queryParam filter[status] string Filter by course status. Example: active
     * @queryParam sort string Sort by a field. Allowed values: slug, name, short_name, status. Prefix with '-' for descending order (e.g., -name for descending by name). Example: name
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/course/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Course::class);
        $courses = QueryBuilder::for(Course::class)
            ->allowedFilters(['slug', 'name', 'short_name', 'status'])
            ->allowedSorts(['slug', 'name', 'short_name', 'status'])
            ->with('categories')
            ->paginate()
            ->appends(request()->query());

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
        $course->load('categories');

        $media = [];
        foreach (['gallery', 'video', 'cover', 'certificate'] as $tag) {
            $media[$tag] = $course->getMedia($tag)
                ->map(fn (Media $m): MediaData => MediaData::fromModel($m, $tag))
                ->toArray();
        }

        return response()->success(ShowCourseData::from([
            ...$course->toArray(),
            'categories' => $course->categories,
            'media' => $media,
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

        return response()->success(ShowCourseData::from($course)->toArray());
    }

    /**
     * Remove the specified course
     *
     * @response 204
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function destroy(Course $course, DeleteCourseAction $action): JsonResponse
    {
        Gate::authorize('delete', $course);
        $action->handle($course);

        return response()->noContentJson();
    }
}
