<?php

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Category\CategorySelectOptionData;
use App\Data\Term\TermSelectOptionData;
use App\Http\Controllers\Controller;

/**
 * @group Admin - Select Options
 *
 * retrieve a list of terms for select options
 *
 * @authenticated
 */
class TermSelectOptionController extends Controller
{

    /**
     * Terms list
     *
     * @queryParam  q string The search query for filtering terms (match name and academic_year). Example: "2023"
     *
     * @responseFile 200 responses/select-options/term.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');

        $terms = \App\Models\Term::query()
            ->when($query, function ($term) use ($query) {
                $term->where(function ($term) use ($query) {
                    $term
                        ->where('name', 'like', '%'.$query.'%')
                        ->orWhere('academic_year', 'like', '%'.$query.'%')
                    ;
                });
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'academic_year']);
        return response()->success(
            TermSelectOptionData::collect($terms)
        );
    }
}
