<?php

declare(strict_types=1);

namespace App\Actions\Admin\Teacher;

use App\Data\Admin\Teacher\CreateTeacherData;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Plank\Mediable\Media;

final readonly class UpdateTeacherAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateTeacherData $data, Teacher $teacher): void
    {
        DB::transaction(function () use ($data, $teacher): void {
            $teacherData               = $data->except('media')->toArray();
            $avatarMedia               = null;
            $teacherData['avatar_url'] = null;
            if ($mediaId = data_get($data->media, 'avatar')) {
                $avatarMedia               = Media::find($mediaId);
                $teacherData['avatar_url'] = $avatarMedia?->getUrl();
            }
            $teacher->update($teacherData);

            $teacher->syncMedia($avatarMedia, 'avatar');

        });
    }
}
