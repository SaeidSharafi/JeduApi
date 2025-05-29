<?php

declare(strict_types=1);

namespace App\Data\File;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\PublicationStatusEnum;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class DigitalAssetListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $is_attachable_to_course,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public ?string $version,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public ?Carbon $published_at,
        public ?int $created_by,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public ?Carbon $created_at,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public ?Carbon $updated_at,
    ) {}
}
