<?php

declare(strict_types=1);

namespace App\Data\Shop\Student\Blocks;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\MoodleActivityStateEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class MoodleActivityData extends Data
{
    public function __construct(
        public string $url,
        public int $cid,
        public string $name,
        public string $type,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public MoodleActivityStateEnum $state,
        public ?string $grade = null,
        public ?string $timecompleted = null,
    ) {}
}
