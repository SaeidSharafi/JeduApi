<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\ProductableEnum;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class ProductableSelectOptionData extends Data
{
    public function __construct(
        public int $id,
        #[MapInputName('name')]
        public string $title,
        #[MapInputName('slug')]
        public string $subtitle,
        #[WithCast(EnumCast::class)]
        #[WithTransformer(TranslatableEnumData::class)]
        public ProductableEnum $type,
    ) {}
}
