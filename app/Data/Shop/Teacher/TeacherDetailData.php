<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\User\GenderEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class TeacherDetailData extends Data
{
    public function __construct(
        public string $uuid,
        public string $first_name,
        public string $last_name,
        public string $bio,
        public string $avatar_url,
        public float $rate,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public GenderEnum $gender,
        public ?array $social_links,
    ) {}
}
