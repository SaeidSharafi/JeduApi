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

            $story = StudentStory::query()->create(
                $data->except('avatar')->toArray()
            );

            // Use 'avatar' as the collection tag for the media
            $story->syncMedia($avatarMedia, 'avatar');

            $story->refresh();

            return $story;
        });
    }
}
