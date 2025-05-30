<?php

declare(strict_types=1);

namespace App\Data\DigitalAsset;

use App\Data\Category\CategoryListItemData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\PublicationStatusEnum;
use App\Traits\ValidatesMetaTags;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class ShowDigitalAssetData extends Data
{
    use ValidatesMetaTags;

    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $version,
        public bool $is_attachable_to_course,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public ?int $created_by,
        public ?string $keywords,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $published_at,
        public ?int $page_count,
        public ?int $duration_seconds,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $created_at,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $updated_at,
        #[DataCollectionOf(CategoryListItemData::class)]
        public ?DataCollection $categories,
        public array $attachments = [],
        public array $media,
    ) {}
}
