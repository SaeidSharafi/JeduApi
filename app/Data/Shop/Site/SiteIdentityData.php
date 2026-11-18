<?php

declare(strict_types=1);

namespace App\Data\Shop\Site;

use Spatie\LaravelData\Data;

final class SiteIdentityData extends Data
{
    public function __construct(
        public string $site_title,
        public ?string $favicon_url,
        public ?string $home_h1,
        public ?string $home_description,
        public ?string $home_meta_title,
        public ?string $home_meta_description,
    ) {}
}
