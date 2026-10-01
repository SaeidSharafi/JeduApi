<?php

declare(strict_types=1);

namespace App\Actions\Admin\LearningPath;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\LearningPath\LearningPathCreateData;
use App\Enums\MediaTagEnum;
use App\Models\LearningPath;
use Illuminate\Support\Facades\DB;

final readonly class CreateLearningPathAction
{
    public function __construct(private SyncLearningPathStepsAction $syncSteps, private GetThumbnailUrlAction $thumbnailUrlAction) {}

    public function handle(LearningPathCreateData $data): LearningPath
    {
        return DB::transaction(function () use ($data): LearningPath {
            $createData                  = $data->except('steps', 'media')->toArray();
            $createData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);
            $learningPath                = LearningPath::create($createData);
            $this->syncMedia($learningPath, $data->media);
            $this->syncSteps->handle($learningPath, $data->steps);

            return $learningPath->fresh();
        });
    }

    /**
     * @param  array<string, array<int, int>>  $media
     */
    private function syncMedia(LearningPath $learningPath, array $media): void
    {
        foreach (MediaTagEnum::cases() as $tag) {
            $mediaIds = $media[$tag->value] ?? [];
            if ($mediaIds !== []) {
                $learningPath->attachMedia($mediaIds, $tag->value);
            }
        }
    }
}
