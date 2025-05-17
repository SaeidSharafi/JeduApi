<?php

namespace App\Data\Course;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\CourseStatusEnum;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use \Spatie\LaravelData\Attributes\Validation;
class CourseResponseData extends Data
{
    /**
     * slug
     * name
     * short_name
     * description
     * default_teahcer_info
     * meta_title
     * meta_description
     * meta_keywords
     * status
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $short_name,
        public ?string $description,
        public ?string $default_teahcer_info,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        #[WithCast(CourseStatusEnum::class),WithTransformer(TranslatableEnumData::class)]
        public CourseStatusEnum $status
    )
    {
    }
}
