<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\TeacherSelectOptionData;
use App\Http\Controllers\Controller;
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
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/teacher.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $query   = request()->string('q', '');
        $perPage = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

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
            ->paginate($perPage, ['id', 'first_name', 'last_name', 'email', 'phone'])
            ->withQueryString();

        return apiResponse()->success(
            TeacherSelectOptionData::collect($teachers)
        );
    }
}
