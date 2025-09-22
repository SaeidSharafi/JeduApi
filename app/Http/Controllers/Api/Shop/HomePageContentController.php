<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\GetHomePageBlockAction;
use App\Actions\Shop\GetHomePageBlocksListAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\HomePageBlock;

/**
 * @group Shop - Home Page Blocks
 *
 * @unauthenticated
 *
 * APIs for managing home page blocks
 */
final class HomePageContentController extends Controller
{
    /**
     * Get home page blocks list
     *
     * Retrieves a simplified list of all home page blocks with id, location, and preset.
     * This is useful for the frontend to get an overview of available blocks without loading
     * the full content.
     *
     * @responseFile 200 responses/shop/home/index.json
     */
    public function index(GetHomePageBlocksListAction $action): ApiResponseInterface
    {
        return response()->success($action->handle());
    }

    /**
     * Get single home page block
     *
     * Retrieves the full formatted content for a specific home page block.
     * This allows the frontend to load individual blocks on demand.
     *
     * @responseFile 200 scenario=banner responses/shop/home/show-banner.json
     * @responseFile 200 scenario=webinar-banner responses/shop/home/show-webinar-banner.json
     * @responseFile 200 scenario=curated-list-products responses/shop/home/show-list-products.json
     * @responseFile 200 scenario=curated-list-categories responses/shop/home/show-list-categories.json
     * @responseFile 200 scenario=dynamic-list-products responses/shop/home/show-dlist-courses.json
     * @responseFile 200 scenario=dynamic-list-blog responses/shop/home/show-dlist-blog.json
     */
    public function show(HomePageBlock $homePageBlock, GetHomePageBlockAction $action): ApiResponseInterface
    {
        return response()->success($action->handle($homePageBlock));
    }
}
