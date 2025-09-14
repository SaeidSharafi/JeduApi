<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Actions\Admin\CollaborationCarousel\CreateCollaborationCarouselAction;
use App\Actions\Admin\CollaborationCarousel\DeleteCollaborationCarouselAction;
use App\Actions\Admin\CollaborationCarousel\UpdateCollaborationCarouselAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\CollaborationCarousel\CollaborationCarouselCreateData;
use App\Data\Admin\CollaborationCarousel\CollaborationCarouselListItemData;
use App\Data\Admin\CollaborationCarousel\CollaborationCarouselData;
use App\Models\CollaborationCarousel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
    use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Settings - Collaboration Carousel
 */
final class CollaborationCarouselController extends Controller
{
    /**
     * Display list of collaboration carousel items.
     *
     * @queryParam filter[title] string Filter by title. Example: Partner
     * @queryParam filter[show_in] string Filter by show_in. can be 'home' or 'course'. Example: home
     * @queryParam filter[is_active] boolean Filter by active status. Example: 1
     *
     * @queryParam sort string Sort by a field. Allowed values: order, title, created_at. Prefix with '-' for descending order (e.g., -title for descending by title). Example: order
     *
     * @responseFile 200 responses/settings/collaboration-carousel/index.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', CollaborationCarousel::class);
        $collaborationCarousels = QueryBuilder::for(CollaborationCarousel::class)
            ->defaultSort('order')
            ->allowedFilters(['title', 'show_in', 'is_active'])
            ->allowedSorts('order', 'title', 'created_at')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();
        return response()->success(CollaborationCarouselListItemData::collect($collaborationCarousels));
    }

    /**
     * Display the specified collaboration carousel item.
     *
     * @urlParam collaboration_carousel required The ID of the collaboration carousel item. Example: 1
     *
     * @responseFile 200 responses/settings/collaboration-carousel/show.json
     */
    public function show(CollaborationCarousel $collaborationCarousel): ApiResponseInterface
    {
        Gate::authorize('view', $collaborationCarousel);
        $collaborationCarousel->load('media');
        return response()->success(CollaborationCarouselData::from([
            ...$collaborationCarousel->toArray(),
            'image' => $collaborationCarousel->getImage(),
        ]));
    }

    /**
     * Store a newly created collaboration carousel item.
     *
     * @responseFile 201 responses/settings/collaboration-carousel/show.json
     */
    public function store(CollaborationCarouselCreateData $data, CreateCollaborationCarouselAction $action): ApiResponseInterface
    {
        Gate::authorize('create', CollaborationCarousel::class);
        $collaborationCarousel = $action->handle($data);
        $collaborationCarousel->load('media');
        return response()->created(CollaborationCarouselData::from([
            ...$collaborationCarousel->toArray(),
            'image' => $collaborationCarousel->getImage(),
        ]));
    }

    /**
     * Update the specified collaboration carousel item.
     *
     * @responseFile 200 responses/settings/collaboration-carousel/show.json
     */
    public function update(CollaborationCarouselCreateData $data, CollaborationCarousel $collaborationCarousel, UpdateCollaborationCarouselAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $collaborationCarousel);
        $collaborationCarousel = $action->handle($collaborationCarousel, $data);
        $collaborationCarousel->load('media');
        return response()->updated(CollaborationCarouselData::from([
            ...$collaborationCarousel->toArray(),
            'image' => $collaborationCarousel->getImage(),
        ]), model: CollaborationCarousel::class);
    }

    /**
     * Remove the specified collaboration carousel item.
     *
     * @response 204
     */
    public function destroy(CollaborationCarousel $collaborationCarousel, DeleteCollaborationCarouselAction $action): JsonResponse
    {
        Gate::authorize('delete', $collaborationCarousel);
        $action->handle($collaborationCarousel);
        return response()->noContentJson();
    }
}
