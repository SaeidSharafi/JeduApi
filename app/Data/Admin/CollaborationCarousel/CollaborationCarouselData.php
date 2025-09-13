<?php

declare(strict_types=1);

namespace App\Data\Admin\CollaborationCarousel;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;
use App\Enums\CollaborationCarouselShowInEnum;

final class CollaborationCarouselData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $caption,
        public ?MediaData $image,
        public ?string $url,
        public CollaborationCarouselShowInEnum $show_in,
        public int $order,
        public bool $is_active,
    ) {}
}
