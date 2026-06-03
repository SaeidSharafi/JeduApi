<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\TeacherSelectOptionData;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
     * @responseFile 200 resources/responses/admin/select-options/teacher.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');
        $limit = request()->integer('limit', 10);

        $teachers = \App\Models\Teacher::query()
            ->when($query, function ($teacher) use ($query): void {
                $teacher->where(function ($teacher) use ($query): void {

                    $teacher->whereLike(DB::raw("CONCAT(first_name, ' ', last_name)"), '%'.$query.'%')
                        ->orWhereLike('email', '%'.$query.'%')
                        ->orWhereLike('phone', '%'.$query.'%');
                });
            })
            ->withMediaAndVariants(['profile'])
            ->orderBy('last_name')
            ->when($limit, fn (Builder $q): Builder => $q->limit($limit))
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        return response()->success(
            TeacherSelectOptionData::collect($teachers)
        );
    }
}
