<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\TermSelectOptionData;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

/**
 * @group Admin - Select Options
 *
 * retrieve a list of terms for select options
 *
 * @authenticated
 */
final class TermSelectOptionController extends Controller
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
        $limit = request()->integer('limit', 10);

        $terms = \App\Models\Term::query()
            ->when($query, function ($term) use ($query): void {
                $term->where(function ($term) use ($query): void {
                    $term
                        ->where('name', 'like', '%'.$query.'%')
                        ->orWhere('academic_year', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->when($limit, fn (Builder $q): Builder => $q->limit($limit))
            ->get(['id', 'name', 'academic_year']);

        return response()->success(
            TermSelectOptionData::collect($terms)
        );
    }
}
