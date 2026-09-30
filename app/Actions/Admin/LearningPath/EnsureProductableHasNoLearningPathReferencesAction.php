<?php

declare(strict_types=1);

namespace App\Actions\Admin\LearningPath;

use App\Enums\System\MorphTypeEnum;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Builder;

final readonly class EnsureProductableHasNoLearningPathReferencesAction
{
    /**
     * Reject deletion when a productable is referenced by one or more paths.
     */
    public function handle(MorphTypeEnum $productableType, int $productableId): void
    {
        $learningPaths = LearningPath::query()
            ->select(['id', 'title', 'slug', 'status'])
            ->whereHas('steps', function (Builder $query) use ($productableType, $productableId): void {
                $query
                    ->where('productable_type', $productableType->value)
                    ->where('productable_id', $productableId);
            })
            ->orderBy('id')
            ->get();

        if ($learningPaths->isEmpty()) {
            return;
        }

        throw new ModelHasRelationshipDataException(
            relatedModel: LearningPath::class,
            errors: [
                'learning_paths' => $learningPaths->map(static fn (LearningPath $learningPath): array => [
                    'id'     => $learningPath->getKey(),
                    'title'  => $learningPath->title,
                    'slug'   => $learningPath->slug,
                    'status' => $learningPath->status->value,
                ])->all(),
            ],
        );
    }
}
