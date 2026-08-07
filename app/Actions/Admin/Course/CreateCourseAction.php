<?php

declare(strict_types=1);

namespace App\Actions\Admin\Course;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\Course\CreateCourseData;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

final readonly class CreateCourseAction
{
    public function __construct(
        private GetThumbnailUrlAction $thumbnailUrlAction
    ) {}

    /**
     * Execute the action.
     */
    public function handle(CreateCourseData $data): void
    {
        DB::transaction(function () use ($data): void {
            $mediaToAttach       = $data->media         ;
            $categoriesToAttach  = $data->categories    ;
            $digitalAssetsAttach = $data->digital_assets;

            $valdiatedData                  = $data->except('media', 'categories', 'digital_assets')->all();
            $valdiatedData['thumbnail_url'] = $this->thumbnailUrlAction->handle($mediaToAttach);

            $course = Course::query()->create($valdiatedData);
            $course->categories()->attach($categoriesToAttach);
            $course->digitalAssets()->attach($digitalAssetsAttach);
            foreach ($mediaToAttach as $tag => $mediaIds) {
                if (is_array($mediaIds)) {
                    foreach ($mediaIds as $mediaId) {
                        $course->attachMedia($mediaId, $tag);
                    }
                }
            }
        });
    }
}
