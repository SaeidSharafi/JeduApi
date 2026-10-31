<?php

declare(strict_types=1);

namespace App\Data\Shop\Site;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

/**
 * A trust badge rendered in the storefront footer.
 *
 * The stored payload keeps the media id in `image` (what the admin panel reads
 * back) and the resolved address in `image_url` (computed on save by
 * UpdateFooterSettingAction). The storefront renders `image`, so that key is fed
 * from `image_url` and never exposes the id.
 */
final class CertificationData extends Data
{
    public function __construct(
        public string $name,
        #[MapInputName('image_url')]
        public ?string $image,
        public ?string $html,
    ) {}
}
