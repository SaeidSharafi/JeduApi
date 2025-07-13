<?php

declare(strict_types=1);

namespace App\Actions\Admin\Teacher;

use App\Data\Admin\Teacher\CreateTeacherData;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

final readonly class UpdateTeacherAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateTeacherData $data, Teacher $teacher): void
    {
        DB::transaction(function () use ($data, $teacher): void {
            $media = $data->media ?? [];
            $teacher->update($data->except('media')->toArray());

            foreach ($media as $tag => $mediaIds) {
                $teacher->syncMedia($mediaIds, $tag);
            }
        });
    }
}
