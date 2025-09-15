<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting\StudentStory;

use App\Data\Admin\Settings\StudentStory\StudentStoryCreateData;
use App\Models\StudentStory;
use Illuminate\Support\Facades\DB;

final class UpdateStudentStoryAction
{
    public function handle(StudentStory $story, StudentStoryCreateData $data): StudentStory
    {
        return DB::transaction(function () use ($story, $data): StudentStory {
            $story->update(
                $data->except('avatar')->toArray()
            );

            $story->syncMedia(data_get($data, 'avatar'), 'avatar');

            $story->refresh();

            return $story;
        });
    }
}
