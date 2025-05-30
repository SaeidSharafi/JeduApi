<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Seminar\CreateSeminarAction;
use App\Actions\Seminar\DeleteSeminarAction;
use App\Actions\Seminar\UpdateSeminarAction;
use App\Contracts\ApiResponseInterface;
use App\Data\MediaData;
use App\Data\Seminar\CreateSeminarData;
use App\Data\Seminar\SeminarListItemData;
use App\Data\Seminar\ShowSeminarData;
use App\Models\Seminar;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Plank\Mediable\Media;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @authenticated admin
 * @group Seminar Manamgemnt
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
            ->paginate(request()->get('per_page', 15));

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
        $seminar->load('categories','digitalAssets');
        $media = [];
        foreach (['gallery', 'video', 'cover'] as $tag) {
            $media[$tag] = $seminar->getMedia($tag)
                ->map(fn (Media $m): MediaData => MediaData::fromModel($m, $tag))
                ->toArray();
        }

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
        $seminar->load('categories','digitalAssets');
        $media = [];
        foreach (['gallery', 'video', 'cover'] as $tag) {
            $media[$tag] = $seminar->getMedia($tag)
                ->map(fn (Media $m): MediaData => MediaData::fromModel($m, $tag))
                ->toArray();
        }
        return response()->success(ShowSeminarData::from(
            [
                ...$seminar->toArray(),
                'categories' => $seminar->categories,
                'media'      => $media,
            ]
        ),message: __('messages.updated', ['model' => __('models.seminar')]));
    }

    /**
     * Remove the specified seminar from database.
     *
     * @response 204
     */
    public function destroy(Seminar $seminar, DeleteSeminarAction $action): JsonResponse
    {
        Gate::authorize('delete', $seminar);
        $action->handle($seminar);

        return response()->noContentJson();
    }
}
