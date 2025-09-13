<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Actions\Admin\Setting\DeleteHomePageBlockAction;
use App\Actions\Admin\Setting\StoreHomePageBlockAction;
use App\Actions\Admin\Setting\UpdateHomePageBlockAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Settings\HomePageBlock\HomePageBlockCreateData;
use App\Data\Admin\Settings\HomePageBlock\HomePageBlockData;
use App\Models\HomePageBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Admin - Settings - Home Page Blocks
 *
 * @authenticated
 *
 * APIs for managing home page blocks
 */
class HomePageBlockController
{

    /**
     * Display a listing of the home page blocks.
     *
     * @responseFile 200 responses/settings/home-page-block/index.json
     * @responseFile 403 responses/403.json
     */
    public function index()
    {
        Gate::authorize('view-any',HomePageBlock::class);

        $blocks = QueryBuilder::for(HomePageBlock::class)
            ->with('media')
            ->orderBy('order')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(HomePageBlockData::collect($blocks));
    }


    /**
     * Store a newly created home page block in storage.
     *
     * @responseFile 200 responses/settings/home-page-block/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 422 responses/422.json
     */
    public function store(
        HomePageBlockCreateData $data,
        StoreHomePageBlockAction $action
    ): ApiResponseInterface {
        Gate::authorize('create', HomePageBlock::class);

        $block = $action->handle($data);

        $responseDto = HomePageBlockData::fromModel($block);
        return response()->created($responseDto);
    }

    /**
     * Display the specified home page block.
     *
     * @responseFile 200 responses/settings/home-page-block/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(HomePageBlock $homePageBlock): ApiResponseInterface
    {
        Gate::authorize('view', $homePageBlock);

        $homePageBlock->load('media');

        return response()->success(HomePageBlockData::fromModel($homePageBlock));
    }

    /**
     * Update the specified home page block in storage.
     *
     * @responseFile 200 responses/settings/home-page-block/show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/422.json
     */
    public function update(
        HomePageBlockCreateData $data,
        HomePageBlock $homePageBlock,
        UpdateHomePageBlockAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $homePageBlock);

        $homePageBlock = $action->handle($homePageBlock, $data);

        return response()->updated(HomePageBlockData::fromModel($homePageBlock), model: HomePageBlock::class);
    }

    /**
     * Remove the specified home page block from storage.
     *
     * @response 204
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function destroy(HomePageBlock $homePageBlock, DeleteHomePageBlockAction $action): JsonResponse
    {
        Gate::authorize('delete', $homePageBlock);

        $action->handle($homePageBlock);

        return response()->noContentJson();
    }
}
