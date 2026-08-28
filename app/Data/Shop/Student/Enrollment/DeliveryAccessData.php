<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Enrollment;

use Spatie\LaravelData\Data;

final class DeliveryAccessData extends Data
{
    public function __construct(
        public string $type,
        public bool $is_ready,
        public ?string $session_label = null,
        public ?string $join_url_path = null,
        public ?string $course_url = null,
        public ?bool $completed = null,
        public ?string $course_grade = null,
        public ?string $license_key = null,
        public ?string $player_url = null,
        public ?string $address = null,
        public ?string $map_url = null,
    ) {}
}
