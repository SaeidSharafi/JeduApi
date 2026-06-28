<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Blocks;

use Spatie\LaravelData\Data;

final class MoodleActivityData extends Data
{
    public function __construct(
        public string $url,
        public int $cid,
        public string $name,
        public string $type,
        public int $state,
        public ?string $grade = null,
        public ?string $timecompleted = null,
    ) {}
}
