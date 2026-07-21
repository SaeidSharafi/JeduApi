<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use Spatie\LaravelData\Data;

final class TeacherCourseItemData extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $start_date,
        public ?string $end_date,
        public bool $is_current,
        public bool $has_grades_enabled,
        public bool $has_attendance_enabled,
        public ?array $product_image = null,
        public ?string $product_delivery_option_uuid = null,
    ) {}
}
