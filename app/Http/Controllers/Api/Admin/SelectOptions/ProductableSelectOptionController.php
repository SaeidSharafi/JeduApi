<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\SelectOptions;

use App\Data\Admin\SelectOptions\ProductableSelectOptionData;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;
use Illuminate\Support\Facades\DB;

/**
 * @group Admin - Select Options
 *
 * @authenticated
 * retrieve a list of productable items (courses, seminars, digital assets) for select options
 */
final class ProductableSelectOptionController extends Controller
{
    /**
     * Productable items list
     *
     * @queryParam  q string The search query for filtering productable items (match name). Example: "advanced"
     * @queryParam  types array The types of productable items to include. Possible values: course, seminar, digital_asset. Example: ["course", "seminar"]
     * @queryParam  limit integer The maximum number of results to return. Default is 15. Example: 10
     *
     * @responseFile 200 responses/select-options/productable.json
     */
    public function __invoke()
    {
        $query = request()->string('q', '');
        $limit = request()->integer('limit', 15);
        $types = request()->array('types') ?: [
            ProductableEnum::COURSE->value,
            ProductableEnum::SEMINAR->value,
            ProductableEnum::DIGITAL_ASSET->value,
        ];

        $queries = [];

        // 1. Build a query for Courses if requested
        if (in_array(ProductableEnum::COURSE->value, $types)) {
            $coursesQuery = Course::query()
                ->select([
                    'id',
                    'full_name as name',
                    'slug',
                    DB::raw("'".ProductableEnum::COURSE->value."' as type"), // Add a literal 'type' column
                ])
                ->when($query, function ($q, $search): void {
                    $q->whereLike('full_name', "%{$search}%")
                        ->orWhereLike('short_name', "%{$search}%");
                });
            $queries[] = $coursesQuery;
        }

        // 2. Build a query for Seminars if requested
        if (in_array(ProductableEnum::SEMINAR->value, $types)) {
            $seminarsQuery = Seminar::query()
                ->select([
                    'id',
                    'full_name as name',
                    'slug',
                    DB::raw("'".ProductableEnum::SEMINAR->value."' as type"),
                ])
                ->when($query, function ($q, $search): void {
                    $q->whereLike('full_name', "%{$search}%")
                        ->orWhereLike('short_name', "%{$search}%");
                });
            $queries[] = $seminarsQuery;
        }

        // 3. Build a query for Digital Assets if requested
        if (in_array(ProductableEnum::DIGITAL_ASSET->value, $types)) {
            $digitalAssetsQuery = DigitalAsset::query()
                ->select([
                    'id',
                    'full_name as name',
                    'slug',
                    DB::raw("'".ProductableEnum::DIGITAL_ASSET->value."' as type"),
                ])
                ->when($query, function ($q, $search): void {
                    $q->whereLike('full_name', "%{$search}%")
                        ->orWhereLike('short_name', "%{$search}%");
                });
            $queries[] = $digitalAssetsQuery;
        }

        // If no types were selected, return an empty collection
        if (empty($queries)) {
            return response()->success(ProductableSelectOptionData::collect([]));
        }

        // 4. Combine the queries using UNION ALL
        $firstQuery = array_shift($queries); // Start with the first query

        foreach ($queries as $unionQuery) {
            $firstQuery->unionAll($unionQuery); // Union the rest
        }

        // 5. Apply the final limit and get the results
        $results = $firstQuery->limit($limit)->get();

        return response()->success(ProductableSelectOptionData::collect($results));

    }
}
