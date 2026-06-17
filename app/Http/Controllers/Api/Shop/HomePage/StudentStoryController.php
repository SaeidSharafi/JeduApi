<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\HomePage\StudentStoryData;
use App\Data\Shop\StudentStoryRequestData;
use App\Enums\System\CacheKeysEnum;
use App\Http\Controllers\Controller;
use App\Models\StudentStory;
use App\Services\SWRCacheService;

/**
 * @group Shop - Home Page
 *
 * APIs for retrieving Home Page Content
 */
final class StudentStoryController extends Controller
{
    /**
     * Student Stories
     *
     * Returns a list of student stories to be displayed on the home page.
     *
     * @responseFile 200 resources/responses/shop/home/student-story.json
     */
    public function __invoke(StudentStoryRequestData $data): ApiResponseInterface
    {
        $cacheKey = CacheKeysEnum::StudentStory->value.':'.md5(serialize($data->toArray()));

        $stories = SWRCacheService::rememberHomepageContent($cacheKey,
            function () use ($data) {
                $stories = StudentStory::query()
                    ->visible()
                    ->when($data->featured_only, fn ($query) => $query->featured())
                    ->when($data->category_slug,
                        fn ($query, $slug) => $query->whereHas('categories', fn ($q) => $q->where('slug', $slug)))
                    ->when($data->course_slug, function ($query, $slug) {
                        $query->whereHas('courses', function ($q) use ($slug) {
                            $q->where('slug', $slug)
                                ->orWhereHas('products', function ($q2) use ($slug) {
                                    $q2->where('slug', $slug);
                                });
                        });
                    })
                    ->orderBy('display_order')
                    ->get();

                if ($stories->isEmpty() && ($data->category_slug || $data->course_slug)) {
                    $stories = StudentStory::query()
                        ->visible()
                        ->featured()
                        ->orderBy('display_order')
                        ->get();
                }

                return StudentStoryData::collect($stories);
            });

        return response()->success($stories);
    }
}
