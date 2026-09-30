<?php

declare(strict_types=1);

namespace App\Actions\Admin\LearningPath;

use App\Data\Admin\LearningPath\LearningPathUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\MediaTagEnum;
use App\Models\LearningPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final readonly class UpdateLearningPathAction
{
    public function __construct(private SyncLearningPathStepsAction $syncSteps) {}

    public function handle(LearningPathUpdateData $data, LearningPath $learningPath): LearningPath
    {
        $this->validateStatusTransition($learningPath, $data->status);

        return DB::transaction(function () use ($data, $learningPath): LearningPath {
            $learningPath->update($data->except('steps', 'media')->toArray());

            if (! $data->media instanceof Optional) {
                foreach (MediaTagEnum::cases() as $tag) {
                    $learningPath->syncMedia($data->media[$tag->value] ?? [], $tag->value);
                }
            }

            $this->syncSteps->handle($learningPath, $data->steps);

            return $learningPath->fresh();
        });
    }

    private function validateStatusTransition(LearningPath $learningPath, string $status): void
    {
        if (
            $learningPath->status !== PublicationStatusEnum::DRAFT
            && PublicationStatusEnum::from($status) === PublicationStatusEnum::DRAFT
        ) {
            throw ValidationException::withMessages([
                'status' => 'A non-draft learning path cannot return to draft status.',
            ]);
        }
    }
}
