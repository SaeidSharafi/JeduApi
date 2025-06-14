<?php

namespace App\Data\Vendor;

use Spatie\LaravelData\Data;

class VendorShortListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $logo_url,
    )
    {
    }
}
