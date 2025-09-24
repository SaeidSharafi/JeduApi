<?php

declare(strict_types=1);

namespace App\Data\Admin\Slider;

use App\Data\Admin\MediaData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\PublicationStatusEnum;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class SliderData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $caption,
        #[WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public ?MediaData $image,
        public ?string $link,
        public int $order,
    ) {}
}
