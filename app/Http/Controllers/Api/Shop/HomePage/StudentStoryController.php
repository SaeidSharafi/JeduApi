<?php

namespace App\Http\Controllers\Api\Shop\HomePage;

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
class StudentStoryController extends Controller
{
    /**
         * List student stories for the shop home page.
         *
         * Returns a JSON success response containing an array of student story objects (mapped via StudentStoryData).
         * Results are served from cache using CacheKeysEnum::StudentStory; the cache key and TTL come from that enum.
         *
         * @return \Illuminate\Http\JsonResponse JSON response with `data` set to an array of student story DTOs.
         */
    public function __invoke()
    {
        $stories = SmartCache::remember(CacheKeysEnum::StudentStory->value, CacheKeysEnum::StudentStory->ttl(),
            function () {
                $stories = StudentStory::query()
                    ->withMedia('avatar')
                    ->visible()
                    ->orderBy('display_order')
                    ->get();
                return $stories->map(fn($story) => StudentStoryData::fromModel($story));
            });
        return response()->success($stories);
    }
}
