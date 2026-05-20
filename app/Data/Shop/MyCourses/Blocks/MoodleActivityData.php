<?php

namespace App\Data\Shop\MyCourses\Blocks;

use Spatie\LaravelData\Data;

class MoodleActivityData extends Data
{
    public function __construct(
        public string $url,
        public int $cid,
        public string $name,
        public string $type,
        public int $state,
        public ?string $timecompleted = null,
    )
    {
    }
}
