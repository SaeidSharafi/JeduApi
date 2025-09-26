<?php

declare(strict_types=1);

namespace App\Data\Admin\Partner;

use App\Data\Admin\MediaData;
use App\Enums\Content\PartnerShowInEnum;
use Spatie\LaravelData\Data;

final class PartnerData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $caption,
        public ?MediaData $image,
        public ?string $url,
        public PartnerShowInEnum $show_in,
        public int $order,
        public bool $is_active,
    ) {}
}
