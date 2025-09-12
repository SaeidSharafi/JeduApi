<?php

declare(strict_types=1);

namespace App\Data\Admin\Slider;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;

final class SliderListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $caption,
        public string $image_url,
        public ?string $link,
        public int $order,
    ) {}
}
