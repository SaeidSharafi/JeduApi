<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Cache\CacheStore;
use App\Data\Shop\HomePage\StudentStoryData;
use App\Data\Shop\StudentStoryRequestData;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use App\Models\StudentStory;

/**
 * @group Shop - Home Page
 *
 * APIs for retrieving Home Page Content
 */
final class StudentStoryController extends Controller
{
    public function __construct(private readonly CacheStore $cache) {}

    /**
     * Student Stories
     *
     * Returns a list of student stories to be displayed on the home page.
     *
     * @responseFile 200 resources/responses/shop/home/student-story.json
     */
    public function __invoke(StudentStoryRequestData $data): ApiResponseInterface
    {
        $hash = md5(serialize($data->toArray()));

        $stories = $this->cache->flexible(CacheKey::StudentStory, ['hash' => $hash],
            function () use ($data) {
                $stories = StudentStory::query()
                    ->visible()
                    ->when($data->featured_only, fn ($query) => $query->featured())
                    ->when($data->category_slug,
                        fn ($query, $slug) => $query->whereHas('categories', fn ($q) => $q->where('slug', $slug)))
                    ->when($data->course_slug, function ($query, $slug): void {
                        $query->whereHas('courses', function ($q) use ($slug): void {
                            $q->where('slug', $slug)
                                ->orWhereHas('products', function ($q2) use ($slug): void {
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

        return apiResponse()->success($stories);
    }
}
