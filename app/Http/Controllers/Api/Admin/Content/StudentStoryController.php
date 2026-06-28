<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

use App\Actions\Admin\Setting\StudentStory\CreateStudentStoryAction;
use App\Actions\Admin\Setting\StudentStory\DeleteStudentStoryAction;
use App\Actions\Admin\Setting\StudentStory\UpdateStudentStoryAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\StudentStory\StudentStoryCreateData;
use App\Data\Admin\Settings\StudentStory\StudentStoryData;
use App\Data\Admin\Settings\StudentStory\StudentStoryListItemData;
use App\Http\Controllers\Controller;
use App\Models\StudentStory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Settings - Student Stories
 *
 * @authenticated
 */
final class StudentStoryController extends Controller
{
    /**
     * List Student Stories
     *
     * @queryParam filter[student_name] string Filter by student name. Example: John Doe
     * @queryParam filter[course_name] string Filter by course name. Example: Laravel Basics
     * @queryParam filter[is_visible] boolean Filter by visibility. Example: 1
     * @queryParam filter[is_featured] boolean Filter by featured status. Example: 1
     * @queryParam filter[course_id] integer Filter by course ID. Example: 5
     * @queryParam filter[category_id] integer Filter by category ID. Example: 3
     * @queryParam sort string Sort by fields. Allowed values: student_name, course_name, display_order, created_at.
     *     Example: -created_at
     * @queryParam per_page integer Number of items per page. Example: 15
     * @queryParam page integer Page number. Example: 1
     *
     * @responseFile 200 resources/responses/admin/settings/student_story/index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', StudentStory::class);

        $stories = QueryBuilder::for(StudentStory::class)
            ->allowedFilters(
                'student_name',
                'course_name',
                AllowedFilter::exact('is_visible'),
                AllowedFilter::exact('is_featured'),
                AllowedFilter::callback('course_id', function ($query, $value): void {
                    $query->whereHas('courses', function ($q) use ($value): void {
                        $q->where('courses.id', $value);
                    });
                }),
                AllowedFilter::callback('category_id', function ($query, $value): void {
                    $query->whereHas('categories', function ($q) use ($value): void {
                        $q->where('categories.id', $value);
                    });
                }),
            )
            ->allowedSorts(['student_name', 'course_name', 'display_order', 'created_at'])
            ->defaultSort('display_order')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(StudentStoryListItemData::collect($stories));
    }

    /**
     * Get a Student Story
     *
     * @responseFile 200 resources/responses/admin/settings/student_story/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function show(StudentStory $studentStory): ApiResponseInterface
    {
        Gate::authorize('view', $studentStory);

        $studentStory->load('media');

        return response()->success(StudentStoryData::fromModel($studentStory));
    }

    /**
     * Create a Student Story
     *
     * @responseFile 201 resources/responses/admin/settings/student_story/show.json
     * @responseFile 422 resources/responses/422.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function store(StudentStoryCreateData $data, CreateStudentStoryAction $action): ApiResponseInterface
    {
        Gate::authorize('create', StudentStory::class);

        $story = $action->handle($data);
        $story->load('media');

        return response()->created(StudentStoryData::fromModel($story));
    }

    /**
     * Update a Student Story
     *
     * @responseFile 200 resources/responses/admin/settings/student_story/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 422 resources/responses/422.json
     * @responseFile 403 resources/responses/403.json
     */
    public function update(
        StudentStoryCreateData $data,
        StudentStory $studentStory,
        UpdateStudentStoryAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $studentStory);
        $updatedStory = $action->handle($studentStory, $data);
        $updatedStory->load('media');

        return response()->success(StudentStoryData::fromModel($updatedStory));
    }

    /**
     * Delete a Student Story
     *
     * @response 204
     *
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     */
    public function destroy(StudentStory $studentStory, DeleteStudentStoryAction $action): JsonResponse
    {
        Gate::authorize('delete', $studentStory);

        $action->handle($studentStory);

        return response()->noContentJson();
    }
}
