<?php

declare(strict_types=1);

namespace App\Data\Shop\MyCourses;

use Spatie\LaravelData\Data;

final class EnrollmentCertificateInfoData extends Data
{
    public function __construct(
        public bool $is_available,
        public ?string $certificate_url,
    ) {}
}
