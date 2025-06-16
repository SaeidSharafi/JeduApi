<?php

namespace App\Data\Teacher;

use App\Data\Transformer\TranslatableEnumData;
use App\Data\User\ShowUserData;
use App\Enums\GenderEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class TeacherListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $first_name,
        public string $last_name,
        public float $rate,
        public string $email,
        public string $phone,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public GenderEnum $gender,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $birth_date,
        public ?array $social_links,
        public ?ShowUserData $user
    ) {
    }
}
