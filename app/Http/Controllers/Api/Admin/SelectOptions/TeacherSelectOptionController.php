<?php

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Category\CategorySelectOptionData;
use App\Data\Teacher\TeacherSelectOptionData;
use App\Data\Term\TermSelectOptionData;
use App\Data\Term\VendorSelectOptionData;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 */
class TeacherSelectOptionController extends Controller
{

    /**
     * Vendors list
     *
     * @queryParam  q string The search query for filtering vendors (match name). Example: "vendor 1"
     *
     * @responseFile 200 responses/select-options/vendor.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');

        $teachers = \App\Models\Teacher::query()
            ->when($query, function ($teacher) use ($query) {
                $teacher->where(function ($teacher) use ($query) {
                    $teacher->whereRaw("concat(first_name, ' ', last_name) like ?", '%'.$query.'%')
                        ->orWhere('email', 'like', '%'.$query.'%')
                        ->orWhere('phone', 'like', '%'.$query.'%')
                    ;
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
