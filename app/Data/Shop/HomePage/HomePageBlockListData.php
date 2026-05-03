<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\HomePageBlockTypeEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class HomePageBlockListData extends Data
{
    public function __construct(
        public int $id,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public HomePageBlockTypeEnum $type,
        public string $location,
        public int $order = 0,
        public ?string $preset = null,
    ) {}
}
