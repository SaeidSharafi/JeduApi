<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting\StudentStory;

use App\Data\Admin\Settings\StudentStory\StudentStoryCreateData;
use App\Models\StudentStory;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final class CreateStudentStoryAction
{
    public function handle(StudentStoryCreateData $data)
    {
        return DB::transaction(function () use ($data): StudentStory {
            $avatarMedia = Media::find($data->avatar);
            $storyData   = $data->except('avatar', 'categories', 'courses')->toArray();
            if ($avatarMedia) {
                $storyData['avatar_url'] = $avatarMedia->getUrl();
            }
            $story = StudentStory::query()->create($storyData);

            $story->syncMedia($avatarMedia, 'avatar');
            $story->courses()->sync($data->courses);
            $story->categories()->sync($data->categories);
            $story->refresh();

            return $story;
        });
    }
}
