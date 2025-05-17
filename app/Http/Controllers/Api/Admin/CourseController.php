<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Data\Course\CourseData;
use App\Data\Course\CourseResponseData;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

class CourseController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index():  ApiResponseInterface
    {
        Gate::authorize('viewAny', Course::class);
        $courses = QueryBuilder::for(Course::class)
            ->allowedFilters(['slug', 'name', 'short_name', 'status'])
            ->allowedSorts(['slug', 'name', 'short_name', 'status'])
            ->paginate()
            ->appends(request()->query());

        return response()->success(data: CourseResponseData::collect($courses)->toArray());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CourseData $data):  ApiResponseInterface
    {
        Gate::authorize('create',Course::class);

        $course = Course::query()->create($data->all());

        return response()->created(CourseResponseData::from($course)->toArray());
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course):  ApiResponseInterface
    {
        Gate::authorize('view',$course);

        return response()->success(CourseResponseData::from($course)->toArray());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course):  ApiResponseInterface
    {
        Gate::authorize('update', $course);

        return response()->success(CourseData::from($course)->toArray());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CourseData $data, Course $course):  ApiResponseInterface
    {
        Gate::authorize('update', $course);
        $course->update($data->all());
        return response()->success(CourseResponseData::from($course)->toArray());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course): JsonResponse
    {
        Gate::authorize('delete', $course);
        $course->delete();
        return response()->noContentJson();
    }
}
