<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\GetHomePageContentAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\CacheKeysEnum;
use App\Http\Controllers\Controller;
use SmartCache\Facades\SmartCache;

/**
 * @group Shop - Home Page
 *
 * @unauthenticated
 *
 * APIs for the home page content
 */
final class HomePageContentController extends Controller
{
    /**
     * Get home page content
     *
     * Retrieves the entire layout and content for the home page. The frontend will render
     * components based on the `type` of each block returned in the response.
     *
     * @responseFile 200 responses/shop/home/index.json
     */
    public function __invoke(GetHomePageContentAction $action): ApiResponseInterface
    {
        return response()->success($action->handle());
    }
}
