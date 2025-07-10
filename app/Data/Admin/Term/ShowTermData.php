<?php

declare(strict_types=1);

namespace App\Data\Admin\Term;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\TermStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class ShowTermData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?TermStatusEnum $status,
        public ?string $academic_year,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $end_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $created_at,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $updated_at,
    ) {}
}
