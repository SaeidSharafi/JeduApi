<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Teacher\TeacherSelectOptionData;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 */
final class TeacherSelectOptionController extends Controller
{
    /**
     * Teachers list
     *
     * @queryParam  q string The search query for filtering teachers (match combined [first_name and last_name],
     *              email and phone. Example: "John Doe"
     *
     * @responseFile 200 responses/select-options/teacher.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');

        $teachers = \App\Models\Teacher::query()
            ->when($query, function ($teacher) use ($query) {
                $teacher->where(function ($teacher) use ($query) {
                    $teacher->whereRaw("concat(first_name, ' ', last_name) like ?", '%'.$query.'%')
                        ->orWhere('email', 'like', '%'.$query.'%')
                        ->orWhere('phone', 'like', '%'.$query.'%');
                });
            })
            ->withMediaAndVariants(['profile'])
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        return response()->success(
            TeacherSelectOptionData::collect($teachers)
        );
    }
}
