<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Term\CreateTermAction;
use App\Actions\Term\DeleteTermAction;
use App\Actions\Term\UpdateTermAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Term\CreateTermData;
use App\Data\Admin\Term\ShowTermData;
use App\Data\Admin\Term\TermListItemData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @group Admin - Term
 *
 * APIs for managing terms
 *
 * @authenticated Staff
 */
final class TermController extends Controller
{
    /**
     * Return a list of the terms.
     *
     * @queryParam filter[name] string Filter by term name. Example: Fall 2024
     * @queryParam filter[status] string Filter by term status. Example: active
     * @queryParam filter[academic_year] string Filter by academic year. Example: 2024-2025
     * @queryParam sort string Sort by a field. Allowed values: name, status, academic_year, start_date, end_date. Prefix with '-' for descending order. Example: -start_date
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/term/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Term::class);
        $terms = \Spatie\QueryBuilder\QueryBuilder::for(Term::class)
            ->allowedFilters(['name',
                AllowedFilter::exact('status'),
                'academic_year'])
            ->allowedSorts(['name', 'status', 'academic_year', 'start_date', 'end_date'])
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(data: TermListItemData::collect($terms)->toArray());
    }

    /**
     * Create a new term.
     *
     * @responseFile 201 responses/201.json
     */
    public function store(CreateTermData $data, CreateTermAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Term::class);
        $action->execute($data);

        return response()->created(model: Term::class);
    }

    /**
     * Return the specified term detail.
     *
     * @responseFile 200 responses/term/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function show(Term $term): ApiResponseInterface
    {
        Gate::authorize('view', $term);

        return response()->success(ShowTermData::from($term));
    }

    /**
     * Update the specified term.
     *
     * @responseFile 200 responses/term/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function update(CreateTermData $data, Term $term, UpdateTermAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $term);
        $term = $action->execute($term, $data);

        return response()->updated(ShowTermData::from($term)->toArray(), model: Term::class);
    }

    /**
     * Remove the specified term
     *
     * @response 204
     *
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function destroy(Term $term, DeleteTermAction $action): \Illuminate\Http\JsonResponse|ApiResponseInterface
    {
        Gate::authorize('delete', $term);
        try {
            $action->execute($term);
        } catch (ModelHasRelationshipDataException $exception) {
            return response()->validationError(message: $exception->getMessage());
        }

        return response()->noContentJson();
    }
}
