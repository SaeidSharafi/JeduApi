<?php

declare(strict_types=1);

namespace App\Data\Admin\Partner;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;
use App\Enums\PartnerShowInEnum;

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
