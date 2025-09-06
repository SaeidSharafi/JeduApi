<?php

declare(strict_types=1);

namespace App\Data\Admin\Teacher;

use App\Data\Admin\User\ShowUserData;
use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\GenderEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class ShowTeacherData extends Data
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $bio,
        public float $rate,
        public string $email,
        public string $phone,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public GenderEnum $gender,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $birth_date,
        public ?array $social_links,
        public ShowUserData $user,
        public array $media = []
    ) {}
}
