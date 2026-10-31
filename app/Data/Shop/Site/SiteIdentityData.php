<?php

declare(strict_types=1);

namespace App\Data\Shop\Site;

use Spatie\LaravelData\Data;

final class SiteIdentityData extends Data
{
    public function __construct(
        public string $site_title,
        public ?string $favicon_url,
    ) {}
}
