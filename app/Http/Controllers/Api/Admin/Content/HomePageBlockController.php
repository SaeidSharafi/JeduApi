<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Content;

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
final class HomePageBlockController
{
    /**
     * Display a listing of the home page blocks.
     *
     * @responseFile 200 responses/settings/home-page-block/index.json
     * @responseFile 403 responses/403.json
     */
    public function index()
    {
        Gate::authorize('view-any', HomePageBlock::class);

        $blocks = QueryBuilder::for(HomePageBlock::class)
            ->with('media')
            ->orderBy('order')
            ->paginate(request()->integer('per_page', 15))
            ->withQueryString();

        return response()->success(HomePageBlockData::collect($blocks));
    }

    /**
     * Store a newly created home page block in storage.
     * <aside class="warning">The structure of `content` object varies based on the type of block being created.</aside>
     * ### type MAIN_CATEGORIES:
     * - `items` (array of integers, required): An array of category IDs to be featured in the block.
     * - `preset` (string, optional): The display preset for the block. Default is 'default'.
     *
     * ### type CURATED_LIST:
     * - `items` (array of integers, required): An array of product IDs to be featured in the block.
     * - `preset` (string, optional): The display preset for the block. Default is 'default'.
     *
     * ### type BANNER:
     * - `image_id` (integer, required): The ID of the image to be used in the banner.
     * - `action` (string, required): The URL or action to be taken when the banner is clicked.
     * - `action_title` (string, required): The title of the action button on the banner.
     * - `content` (string, optional): Additional content or description for the banner.
     * - `preset` (string, optional): The display preset for the banner. Default is 'default'.
     *
     * ### type WEBINAR_BANNER:
     * - `image_id` (integer, required): The ID of the image to be used in the webinar banner.
     * - `product_id` (integer, required): The ID of the webinar product.
     * - `text` (string, required): The text to be displayed on the webinar banner.
     * - `action` (string, required): The URL or action to be taken when the banner is clicked.
     * - `action_title` (string, required): The title of the action button on the banner.
     * - `preset` (string, optional): The display preset for the webinar banner. Default is 'default'.
     *
     * ### type DYNAMIC_LIST:
     * - `category_ids` (array, required): An array of category IDs to filter the items. If empty, items from all categories will be considered.
     * - `item_type` (string, required): The type of items to display can be one of (course_products, seminar_products, digital_asset_products, blog_post, all_products  :
     *     - `course_products` = Products where productable_type = Course
     *     - `seminar_products` = Products where productable_type = Seminar
     *     - `digital_asset_products` = Products where productable_type = DigitalAsset
     *     - `blog_post` = Actual blog posts (not products)
     *     - `all_products` = All products regardless of productable_type
     * - `sort_by` (string, required): The criteria for sorting the items, can be one of:
     *     - `created_at:desc` = Newest first
     *     - `created_at:asc` = Oldest first
     *     - `updated_at:desc` = Recently updated first
     *     - `updated_at:asc` = Least recently updated first
     *     - `name:asc` = Alphabetical A-Z
     *     - `name:desc` = Alphabetical Z-A
     *     - `popular` = Most popular items based on order count (only for products)
     *     - `featured` = Featured items
     * - `limit` (integer, required): The maximum number of items to display in the block. Must be between 1 and 20.
     * - `preset` (string, optional): The display preset for the dynamic list. Default is 'default'.
     *
     *
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
     *
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
