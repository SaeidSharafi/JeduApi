<?php

declare(strict_types=1);

namespace App\Data\Shop\Student;

use Spatie\LaravelData\Data;

final class StudentDigitalAssetData extends Data
{
    public function __construct(
        public string $uuid,
        public string $enrollment_uuid,
        public string $name,
        public ?string $thumbnail_url,
        public ?string $file_type,
        public ?string $file_type_label,
        public ?int $size_bytes,
        public ?string $size_label,
        public string $download_url,
    ) {}
}
