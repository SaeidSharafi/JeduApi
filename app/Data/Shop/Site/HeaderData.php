<?php

declare(strict_types=1);

namespace App\Data\Shop\Site;

use Spatie\LaravelData\Data;

final class HeaderData extends Data
{
    public function __construct(
        public ?string $logo_url,
        public array $navigation_links,
        public string $contact_phone,
        public string $contact_email
    ) {}
}
