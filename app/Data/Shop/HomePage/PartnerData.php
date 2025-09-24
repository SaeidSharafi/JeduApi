<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;
use App\Enums\PartnerShowInEnum;

final class PartnerData extends Data
{
    public function __construct(
        public string $title,
        public ?string $caption,
        public ?string $image_url,
        public ?string $url,
        public int $order,
    ) {}
}
