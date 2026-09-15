<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\TermSelectOptionData;
use App\Http\Controllers\Controller;

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
     * @queryParam  page integer The page number for pagination. Example: 2
     * @queryParam  per_page integer The number of results per page. Default is 15. Example: 10
     *
     * @responseFile 200 resources/responses/admin/select-options/term.json
     */
    public function __invoke(): \App\Contracts\ApiResponseInterface
    {
        $query   = request()->string('q', '');
        $perPage = request()->integer('per_page', config('app.page_size')) ?: (int) config('app.page_size');

        $terms = \App\Models\Term::query()
            ->when($query, function ($term) use ($query): void {
                $term->where(function ($term) use ($query): void {
                    $term
                        ->whereLike('name', '%'.$query.'%')
                        ->orWhereLike('academic_year', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'academic_year'])
            ->withQueryString();

        return apiResponse()->success(
            TermSelectOptionData::collect($terms)
        );
    }
}
