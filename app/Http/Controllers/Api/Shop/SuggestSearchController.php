<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use Illuminate\Http\Request;

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
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     "Laptop Gaming",
     *     "Laptop Business",
     *     "Laptop HP"
     *   ]
     * }
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, GlobalSearchService $service)
    {
        $request->validate([
            'q'     => 'required|string|min:2|max:255',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $query = $request->input('q');
        $limit = $request->input('limit', 5);

        $suggestions = $service->suggest($query, $limit);

        return response()->success($suggestions);
    }
}
