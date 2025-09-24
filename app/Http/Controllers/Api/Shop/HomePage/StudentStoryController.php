<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\HomePage;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\HomePage\StudentStoryData;
use App\Enums\CacheKeysEnum;
use App\Http\Controllers\Controller;
use App\Models\StudentStory;
use SmartCache\Facades\SmartCache;

/**
 * @group Shop - Home Page
 *
 * APIs for retrieving Home Page Content
 */
final class StudentStoryController extends Controller
{
    /**
     * List Student Stories
     *
     * Returns a list of student stories to be displayed on the home page.
     *
     * @response {
     *  "message": "عملیات با موفقیت انجام شد.",
     *  "data": [
     *      {
     *          "student_name": "فراگیر یک",
     *          "avatar_url": "http://jedu.test/storage/fake-media/placeholder1.jpg",
     *          "course_name": "دوره حسابداری",
     *          "course_url": "http://rempel.com/sunt-nihil-accusantium-harum-mollitia",
     *          "story_text": "داستان فراگیر یک",
     *          "display_order": 0
     *      },
     *      {
     *          "student_name": "فراگیر دو",
     *          "avatar_url": "http://jedu.test/storage/fake-media/placeholder2.jpg",
     *          "course_name": "دوره حسابداری",
     *          "course_url": "http://rempel.com/sunt-nihil-accusantium-harum-mollitia",
     *          "story_text": "داستان فراگیر دو",
     *          "display_order": 0
     *      }
     *  ],
     *  "metadata": []
     * }
     */
    public function __invoke(): ApiResponseInterface
    {
        $stories = SmartCache::remember(CacheKeysEnum::StudentStory->value, CacheKeysEnum::StudentStory->ttl(),
            function () {
                $stories = StudentStory::query()
                    ->withMedia('avatar')
                    ->visible()
                    ->orderBy('display_order')
                    ->get();

                return $stories->map(fn ($story) => StudentStoryData::fromModel($story));
            });

        return response()->success($stories);
    }
}
