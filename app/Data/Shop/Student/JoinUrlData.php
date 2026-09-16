<?php

declare(strict_types=1);

namespace App\Data\Shop\Student;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class JoinUrlData extends Data
{
    public function __construct(
        public string $url,
        public string $type,
        public ?Verta $expires_at = null,
    ) {}
}
