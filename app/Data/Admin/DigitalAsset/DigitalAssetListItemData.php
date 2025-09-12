<?php

declare(strict_types=1);

namespace App\Data\Admin\DigitalAsset;

use App\Contracts\ProductableDataContract;
use App\Contracts\ReviewableDataContract;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\PublicationStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class DigitalAssetListItemData extends Data implements ProductableDataContract, ReviewableDataContract
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $is_attachable_to_course,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public ?string $version,
        public ?Verta $published_at,
        public ?int $created_by,
        public ?Verta $created_at,
        public ?Verta $updated_at,
    ) {}
}
