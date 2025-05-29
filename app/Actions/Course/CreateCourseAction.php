<?php

declare(strict_types=1);

namespace App\Actions\Course;

use App\Data\Course\CreateCourseData;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

final readonly class CreateCourseAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateCourseData $data): void
    {
        DB::transaction(function () use ($data): void {
            $mediaToAttach       = $data->media          ?? [];
            $categoriesToAttach  = $data->categories     ?? [];
            $digitalAssetsAttach = $data->digital_assets ?? [];
            $course              = Course::query()->create($data->except('media', 'categories', 'digital_assets')->all());
            $course->categories()->attach($categoriesToAttach);
            $course->digitalAssets()->attach($digitalAssetsAttach);
            foreach ($mediaToAttach as $tag => $mediaIds) {
                if (is_array($mediaIds)) {
                    foreach ($mediaIds as $mediaId) {
                        $media = \Plank\Mediable\Media::find($mediaId);
                        if ($media) {
                            $course->attachMedia($media, $tag);
                        }
                    }
                }
            }
        });
    }
}
