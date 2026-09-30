<?php

declare(strict_types=1);

namespace App\Data\Admin\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\LearningPath;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class LearningPathListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public int $display_order,
        public PublicationStatusEnum $status,
        public int $steps_count = 0,
        public ?Verta $created_at = null,
        public ?Verta $updated_at = null,
    ) {}

    public static function fromModel(LearningPath $learningPath): self
    {
        return self::factory()->withoutMagicalCreation()->from([
            ...$learningPath->toArray(),
            'steps_count' => (int) ($learningPath->steps_count ?? $learningPath->steps()->count()),
            'created_at'  => $learningPath->created_at ? Verta::instance($learningPath->created_at) : null,
            'updated_at'  => $learningPath->updated_at ? Verta::instance($learningPath->updated_at) : null,
        ]);
    }
}
