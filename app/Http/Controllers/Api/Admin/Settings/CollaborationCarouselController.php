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

final class CollaborationCarouselController extends Controller
{
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

    public function show(CollaborationCarousel $collaborationCarousel): ApiResponseInterface
    {
        Gate::authorize('view', $collaborationCarousel);
        $collaborationCarousel->load('media');
        return response()->success(CollaborationCarouselData::from([
            ...$collaborationCarousel->toArray(),
            'image' => $collaborationCarousel->getImage(),
        ]));
    }

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

    public function destroy(CollaborationCarousel $collaborationCarousel, DeleteCollaborationCarouselAction $action): JsonResponse
    {
        Gate::authorize('delete', $collaborationCarousel);
        $action->handle($collaborationCarousel);
        return response()->noContentJson();
    }
}
