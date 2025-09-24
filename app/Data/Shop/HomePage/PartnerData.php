<?php

declare(strict_types=1);

namespace App\Data\Shop\HomePage;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Data;
use App\Enums\PartnerShowInEnum;

final class PartnerData extends Data
{
    /**
     * Create a PartnerData instance containing display information for a homepage partner.
     *
     * @param string $title The partner's display title.
     * @param string|null $caption Optional caption or subtitle for the partner.
     * @param string|null $image_url Optional URL to the partner's logo or image.
     * @param string|null $url Optional link to the partner's website or profile.
     * @param int $order Numeric ordering value used to sort partners when rendering.
     */
    public function __construct(
        public string $title,
        public ?string $caption,
        public ?string $image_url,
        public ?string $url,
        public int $order,
    ) {}
}
