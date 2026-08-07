<?php

declare(strict_types=1);

namespace App\Actions\Admin\Course;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\Course\CreateCourseData;
use App\Enums\MediaTagEnum;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCourseAction
{
    public function __construct(
        private GetThumbnailUrlAction $thumbnailUrlAction
    ) {}

    /**
     * Execute the action.
     */
    public function handle(CreateCourseData $data, Course $course): void
    {
        DB::transaction(function () use ($data, $course): void {
            $valdiatedData                  = $data->except('media', 'categories', 'digital_assets')->all();
            $valdiatedData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);
            $course->update($valdiatedData);
            $course->products()->update(['slug' => $data->slug]);
            $mediaInput    = $data->media         ;
            $categories    = $data->categories    ;
            $digitalAssets = $data->digital_assets;
            $course->categories()->sync($categories);
            $course->digitalAssets()->sync($digitalAssets);

            foreach (MediaTagEnum::getAllValues() as $tag) {
                $mediaIds = $mediaInput[$tag] ?? null;
                if (is_array($mediaIds)) {
                    $course->syncMedia($mediaIds, $tag);
                }
            }
        });
    }
}
