<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Search\SearchSuggestRequestData;
use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;

/**
 * @group Shop - Search
 *
 * APIs for search suggestions and autocomplete
 */
final class SuggestSearchController extends Controller
{
    /**
     * Get Search Suggestions
     *
     * Get autocomplete suggestions for a search query.
     *
     * @queryParam q string required The search query. Example: "lap"
     * @queryParam limit int The maximum number of suggestions to return. Default is 5. Example: 10
     *
     * @responseFile 200 resources/responses/shop/suggest-search.json
     */
    public function __invoke(SearchSuggestRequestData $requestData, GlobalSearchService $service): ApiResponseInterface
    {
        $suggestions = $service->suggest($requestData->q, $requestData->limit);

        return apiResponse()->success($suggestions);
    }
}
