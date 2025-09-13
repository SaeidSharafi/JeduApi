<?php

declare(strict_types=1);

namespace App\Data\Admin\CollaborationCarousel;

use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Data;
use App\Enums\CollaborationCarouselShowInEnum;

final class CollaborationCarouselListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $caption,
        public ?string $image_url,
        public ?string $url,
        public CollaborationCarouselShowInEnum $show_in,
        public int $order,
        public bool $is_active,
    ) {
    }

}
