<?php

declare(strict_types=1);

namespace App\Data\Course;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\PublicationStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class ResponseCourseData extends Data
{
    public function __construct(
        public string $slug,
        public string $full_name, // Changed from name
        public string $short_name,
        public ?string $description,
        public ?int $duration,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public CourseDifficultyLevelEnum $difficulty_level,
        public ?string $career_prospects_text,
        public ?string $curriculum_summary_text,
        public ?array $outcomes_json,
        public ?string $default_teacher_info,
        public ?array $additional_info,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public ?array $properties,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public array $media = [],
    ) {}
}
