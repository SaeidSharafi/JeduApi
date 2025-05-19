<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Data\Course\CourseData;
use App\Data\Course\CourseResponseData;
use App\Data\MediaData;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
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
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', Course::class);
        $courses = QueryBuilder::for(Course::class)
            ->allowedFilters(['slug', 'name', 'short_name', 'status'])
            ->allowedSorts(['slug', 'name', 'short_name', 'status'])
            ->paginate()
            ->appends(request()->query());

        return Response::success(data: CourseResponseData::collect($courses)->toArray());
    }

    /**
     * Create a new course.
     */
    public function store(CourseData $data): ApiResponseInterface
    {
        Gate::authorize('create', Course::class);

        $mediaInput = $data->only('media')->all() ?? [];
        $course = Course::query()->create($data->except('media')->all());

        // Attach media by tag if media input is provided (expects array: tag => [media_id,...])
        foreach ($mediaInput as $tag => $mediaIds) {
            if (is_array($mediaIds)) {
                foreach ($mediaIds as $mediaId) {
                    $media = \Plank\Mediable\Media::find($mediaId);
                    if ($media) {
                        $course->attachMedia($media, $tag);
                    }
                }
            }
        }

        return response()->created();
    }

    /**
     *  return the specified course detail.
     */
    public function show(Course $course): ApiResponseInterface
    {
        Gate::authorize('view', $course);

        $media = [];
        foreach (['gallery', 'video', 'cover', 'certificate'] as $tag) {
            $media[$tag] = $course->getMedia($tag)
                ->map(fn($m) => MediaData::fromModel($m, $tag))
                ->toArray();
        }

        return response()->success(CourseResponseData::from([
            ...$course->toArray(),
            'media' => $media,
        ])->toArray());
    }

    /**
     * return the specified course detail for edit.
     */
    public function edit(Course $course): ApiResponseInterface
    {
        Gate::authorize('update', $course);

        $media = [];
        foreach (['gallery', 'video', 'cover', 'certificate'] as $tag) {
            $media[$tag] = $course->getMedia($tag)
                ->map(fn($m) => MediaData::fromModel($m, $tag))
                ->toArray();
        }

        return response()->success(CourseData::from([
            ...$course->toArray(),
            'media' => $media,
        ]));
    }

    /**
     * Update the specified course.
     */
    public function update(CourseData $data, Course $course): ApiResponseInterface
    {
        Gate::authorize('update', $course);

        $course->update($data->except('media')->all());

        $mediaInput = $data->media ?? [];

        // Sync media by tag if media input is provided (expects array: tag => [media_id,...])
        foreach (['gallery', 'video', 'thumbnail', 'certificate'] as $tag) {
            $mediaIds = $mediaInput[$tag] ?? null;
            if (is_array($mediaIds)) {
                $course->syncMedia($mediaIds, $tag);
            }
        }

        return response()->success(CourseResponseData::from($course)->toArray());
    }

    /**
     * Remove the specified course
     */
    public function destroy(Course $course): JsonResponse
    {
        Gate::authorize('delete', $course);
        $course->delete();

        return response()->noContentJson();
    }
}
