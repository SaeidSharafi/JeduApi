<?php

declare(strict_types=1);

namespace App\Actions\Admin\Teacher;

use App\Data\Admin\Teacher\CreateTeacherData;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final readonly class CreateTeacherAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateTeacherData $data): void
    {
        DB::transaction(function () use ($data): void {
            $avatarMedia = null;
            $teacherData = $data->except('media')->toArray();
            if ($mediaId = data_get($data->media, 'avatar')) {
                $avatarMedia               = Media::find($mediaId);
                $teacherData['avatar_url'] = $avatarMedia?->getUrl();
            }
            $teacher = Teacher::query()->create($teacherData);

            if ($avatarMedia) {
                $teacher->attachMedia($avatarMedia, 'avatar');
            }

        });
    }
}
