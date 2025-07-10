<?php

declare(strict_types=1);

namespace App\Actions\Course;

use App\Data\Admin\Course\CreateCourseData;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCourseAction
{
    /**
     * Execute the action.
     */
    public function handle(CreateCourseData $data, Course $course): void
    {
        DB::transaction(function () use ($data, $course): void {
            $course->update($data->except('media', 'categories', 'digital_assets')->all());

            $mediaInput    = $data->media          ?? [];
            $categories    = $data->categories     ?? [];
            $digitalAssets = $data->digital_assets ?? [];
            $course->categories()->sync($categories);
            $course->digitalAssets()->sync($digitalAssets);

            foreach (['gallery', 'video', 'cover', 'certificate'] as $tag) {
                $mediaIds = $mediaInput[$tag] ?? null;
                if (is_array($mediaIds)) {
                    $course->syncMedia($mediaIds, $tag);
                }
            }
        });
    }
}
