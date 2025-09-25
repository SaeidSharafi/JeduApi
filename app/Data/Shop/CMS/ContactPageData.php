<?php

declare(strict_types=1);

namespace App\Data\Shop\CMS;

use Spatie\LaravelData\Data;

final class ContactPageData extends Data
{
    public function __construct(
        public string $title,
        public string $subtitle,
        public array $main_links,
        public array $social_links,
        public string $address,
        public string $phone,
        public string $email,
        public string $map_embed_url,
    ) {}

    /**
     * Create ContactPageData from settings array.
     */
    public static function fromSetting(array $setting): self
    {
        return new self(
            title: data_get($setting, 'title', ''),
            subtitle: data_get($setting, 'subtitle', ''),
            main_links: data_get($setting, 'main_links', []),
            social_links: data_get($setting, 'social_links', []),
            address: data_get($setting, 'address', ''),
            phone: data_get($setting, 'phone', ''),
            email: data_get($setting, 'email', ''),
            map_embed_url: data_get($setting, 'map_embed_url', ''),
        );
    }
}
