<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting\StudentStory;

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Settings\StudentStory\StudentStoryCreateData;
use App\Enums\System\CacheTag;
use App\Models\StudentStory;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final class UpdateStudentStoryAction
{
    public function __construct(private readonly CacheStore $cache) {}

    public function handle(StudentStory $story, StudentStoryCreateData $data): StudentStory
    {
        $story = DB::transaction(function () use ($story, $data): StudentStory {
            $storyData               = $data->except('avatar', 'categories', 'courses')->toArray();
            $storyData['avatar_url'] = null;
            if (data_get($data, 'avatar')) {
                $avatarMedia             = Media::find($data->avatar);
                $storyData['avatar_url'] = $avatarMedia->getUrl();
            }
            $story->update($storyData);

            $story->syncMedia(data_get($data, 'avatar'), 'avatar');
            $story->courses()->sync($data->courses);
            $story->categories()->sync($data->categories);

            return $story->refresh();
        });

        $this->cache->invalidate(CacheTag::HomePage);

        return $story;
    }
}
