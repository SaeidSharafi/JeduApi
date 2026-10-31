<?php

declare(strict_types=1);

namespace App\Data\Shop\Site;

use Spatie\LaravelData\Data;

/**
 * A trust badge rendered in the storefront footer.
 *
 * The stored payload keeps the media id in `image` (what the admin panel reads
 * back) and the resolved address in `image_url` (computed on save by
 * UpdateFooterSettingAction). The storefront renders `image_url`, so the id is
 * never exposed publicly.
 */
final class CertificationData extends Data
{
    public function __construct(
        public string $name,
        public ?string $image_url,
        public ?string $html,
    ) {}
}
