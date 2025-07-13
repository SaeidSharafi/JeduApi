<?php

declare(strict_types=1);

namespace App\Actions\Admin\Teacher;

use App\Data\Admin\Teacher\CreateTeacherData;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

final readonly class CreateTeacherAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateTeacherData $data): void
    {
        DB::transaction(function () use ($data): void {
            $media   = $data->media ?? [];
            $teacher = Teacher::query()->create($data->except('media')->toArray());

            foreach ($media as $tag => $mediaIds) {
                $teacher->attachMedia($mediaIds, $tag);
            }
        });
    }
}
