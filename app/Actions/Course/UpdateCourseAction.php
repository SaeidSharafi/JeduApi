<?php

declare(strict_types=1);

namespace App\Actions\Course;

use App\Data\Course\CreateCourseData;
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
            $course->update($data->except('media', 'categories')->all());

            $mediaInput = $data->media ?? [];
            $categories = $data->categories ?? [];
            $course->categories()->sync($categories);
            foreach (['gallery', 'video', 'thumbnail', 'certificate'] as $tag) {
                $mediaIds = $mediaInput[$tag] ?? null;
                if (is_array($mediaIds)) {
                    $course->syncMedia($mediaIds, $tag);
                }
            }
        });
    }
}
