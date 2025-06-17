<?php

declare(strict_types=1);

namespace App\Data\Vendor;

use Spatie\LaravelData\Data;

final class VendorListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $phone2,
        public ?string $address,
        public ?string $map_location,
        public ?string $logo_url,
        public ?string $favicon_url,
        public ?array $social_links,
        public ?array $theme_options
    ) {}
}
