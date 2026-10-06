<?php

declare(strict_types=1);

namespace App\Data\Shop\CMS;

use Spatie\LaravelData\Data;

final class ContactPageData extends Data
{
    public function __construct(
        public array $addresses,
        public string $working_hours,
        public string $support_email,
        public array $social_media_links,
    ) {}

    /**
     * Create ContactPageData from settings array.
     */
    public static function fromSetting(array $setting): self
    {
        return new self(
            addresses: $setting['addresses'] ?? [],
            working_hours: $setting['working_hours'] ?? '',
            support_email: $setting['support_email'] ?? '',
            social_media_links: $setting['social_media_links'] ?? [],
        );
    }
}
