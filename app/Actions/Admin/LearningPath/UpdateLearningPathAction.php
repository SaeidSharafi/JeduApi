<?php

declare(strict_types=1);

namespace App\Actions\Admin\LearningPath;

use App\Actions\Admin\GetThumbnailUrlAction;
use App\Data\Admin\LearningPath\LearningPathUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\MediaTagEnum;
use App\Models\LearningPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateLearningPathAction
{
    public function __construct(private SyncLearningPathStepsAction $syncSteps, private GetThumbnailUrlAction $thumbnailUrlAction) {}

    public function handle(LearningPathUpdateData $data, LearningPath $learningPath): LearningPath
    {
        $this->validateStatusTransition($learningPath, $data->status);

        return DB::transaction(function () use ($data, $learningPath): LearningPath {
            $validatedData                  = $data->except('steps', 'media')->toArray();
            $validatedData['thumbnail_url'] = $this->thumbnailUrlAction->handle($data->media);

            $learningPath->update($validatedData);

            foreach (MediaTagEnum::cases() as $tag) {
                $learningPath->syncMedia($data->media[$tag->value] ?? [], $tag->value);
            }

            $this->syncSteps->handle($learningPath, $data->steps);

            return $learningPath->fresh();
        });
    }

    private function validateStatusTransition(LearningPath $learningPath, string $status): void
    {
        $currentStatus = $learningPath->status;
        $nextStatus    = PublicationStatusEnum::from($status);

        if ($currentStatus !== PublicationStatusEnum::DRAFT && $nextStatus === PublicationStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'A non-draft learning path cannot return to draft status.',
            ]);
        }

        if ($currentStatus === PublicationStatusEnum::DRAFT && $nextStatus === PublicationStatusEnum::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'Only published learning paths can be archived.',
            ]);
        }

        if ($currentStatus === PublicationStatusEnum::PUBLISHED && $nextStatus === PublicationStatusEnum::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'Use the archive operation to retire a published learning path.',
            ]);
        }

        if ($currentStatus === PublicationStatusEnum::ARCHIVED && $nextStatus !== PublicationStatusEnum::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'An archived learning path cannot return to another lifecycle state.',
            ]);
        }
    }
}
