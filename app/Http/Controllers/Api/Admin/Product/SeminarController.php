<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Product;

use App\Actions\Admin\Seminar\CreateSeminarAction;
use App\Actions\Admin\Seminar\DeleteSeminarAction;
use App\Actions\Admin\Seminar\UpdateSeminarAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Seminar\CreateSeminarData;
use App\Data\Admin\Seminar\SeminarListItemData;
use App\Data\Admin\Seminar\ShowSeminarData;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Http\Controllers\Controller;
use App\Models\Seminar;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @authenticated staff
 *
 * @group Admin - Seminar Manamgemnt
 * APIs for managing seminars
 */
final class SeminarController extends Controller
{
    /**
     * Retrun a listing of the seminars.
     *
     * @queryParam filter[full_name] string Filter by seminar full name. Example: Introduction to Programming
     * @queryParam filter[short_name] string Filter by seminar short name. Example: IntroProg
     * @queryParam filter[slug] string Filter by seminar slug. Example: intro-to-programming
     * @queryParam sort string Sort by a field. Allowed values: full_name, short_name, slug,
     *              created_at, updated_at. Prefix with '-' for descending order
     *              (e.g., -created_at for descending by creation date).
     *              Example: -created_at
     * @queryParam page integer Page number for pagination. Example: 2
     * @queryParam per_page integer Number of results per page. Example: 15
     *
     * @responseFile 200 responses/seminar/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('view-any', Seminar::class);
        $seminars = QueryBuilder::for(Seminar::class)
            ->allowedFilters(['full_name', 'short_name', 'slug'])
            ->allowedSorts(['full_name', 'short_name', 'slug', 'created_at', 'updated_at'])
            ->defaultSort('-created_at')
            ->with('categories', 'digitalAssets')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(SeminarListItemData::collect($seminars));
    }

    /**
     * Store a newly created semianr in database.
     *
     * @response 201
     */
    public function store(CreateSeminarData $data, CreateSeminarAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Seminar::class);
        $action->handle($data);

        return response()->created(message: __('messages.created', ['model' => __('models.seminar')]));
    }

    /**
     * Display the specified seminar.
     *
     * @responseFile 200 responses/seminar/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(Seminar $seminar): ApiResponseInterface
    {
        Gate::authorize('view', $seminar);
        $seminar
            ->load('categories', 'digitalAssets')
            ->loadMediaWithVariantsMatchAll();
        $media = $seminar->getAllMedia();

        return response()->success(
            ShowSeminarData::from(
                [
                    ...$seminar->toArray(),
                    'categories' => $seminar->categories,
                    'media'      => $media,
                ]
            )
        );
    }

    /**
     * Update the specified seminar in database.
     *
     * @responseFile 200 responses/seminar/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function update(CreateSeminarData $data, Seminar $seminar, UpdateSeminarAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $seminar);
        $action->handle($data, $seminar);
        $seminar
            ->load('categories', 'digitalAssets')
            ->loadMediaWithVariantsMatchAll();
        $media = $seminar->getAllMedia();

        return response()->success(ShowSeminarData::from(
            [
                ...$seminar->toArray(),
                'categories' => $seminar->categories,
                'media'      => $media,
            ]
        ), message: __('messages.updated', ['model' => __('models.seminar')]));
    }

    /**
     * Remove the specified seminar from database.
     *
     * @response 204
     */
    public function destroy(Seminar $seminar, DeleteSeminarAction $action): JsonResponse|ApiResponseInterface
    {
        Gate::authorize('delete', $seminar);
        try {
            $action->handle($seminar);
        } catch (ModelHasRelationshipDataException $exception) {
            return response()->validationError(message: $exception->getMessage());
        }

        return response()->noContentJson();
    }
}
