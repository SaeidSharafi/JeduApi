<?php

declare(strict_types=1);

namespace App\Data\Admin\Category;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\PublicationStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class CategoryListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public ?string $image_url,
        public ?string $icon_url,
        public ?string $educational_calendar_url,
        public ?int $created_by,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public ?Verta $created_at,
        #[WithTransformer(\Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public ?Verta $updated_at,
    ) {}
}
