<?php

namespace App\Http\Controllers\Api\Admin;

use App\Data\Course\CourseData;
use App\Data\Course\CourseResponseData;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use function Pest\Laravel\json;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $courses = QueryBuilder::for(Course::class)
            ->allowedFilters(['slug', 'name', 'short_name', 'status'])
            ->allowedSorts(['slug', 'name', 'short_name', 'status'])
            ->paginate()
            ->appends(request()->query());

        return response()->success(data: CourseResponseData::collect($courses)->toArray());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CourseData $data)
    {
        $course = Course::query()->create($data->all());

        return response()->created(CourseResponseData::from($course)->toArray());
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        return response()->success(CourseResponseData::from($course)->toArray());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Course $course)
    {
        return response()->success(CourseData::from($course)->toArray());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CourseData $data, Course $course)
    {
        $course->update($data->all());
        return response()->success(CourseResponseData::from($course)->toArray());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        $course->delete();
        return response()->noContent();
    }
}
