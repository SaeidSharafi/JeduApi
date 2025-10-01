<?php

declare(strict_types=1);

namespace App\Data\Admin\Product;

use App\Enums\Product\RelationTypeEnum;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;

final class RelatedProductSyncData extends Data
{
    public function __construct(
        /** @var array<int> */
        #[Exists('products', 'id')]
        public array $product_ids,
        #[Enum(RelationTypeEnum::class)]
        public RelationTypeEnum $relation_type
    ) {}
}
