<?php

namespace App\Data\Category;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\PublicationStatusEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class CategoryListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public ?string $image_url = null,
        public ?string $icon_url = null,
        public ?int $created_by = null,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public \DateTimeInterface $created_at,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public \DateTimeInterface $updated_at,
    )
    {
    }
}
