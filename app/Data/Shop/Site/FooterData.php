<?php

declare(strict_types=1);

namespace App\Data\Shop\Site;

use App\Data\Admin\Settings\SocialMediaLinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class FooterData extends Data
{
    public function __construct(
        public ?string $logo_url,
        public ?string $logo_alt,
        public string $caption,
        public string $support_link,
        public string $support_email_address,
        public array $addresses,
        public array $categories,
        public array $main_links,
        #[DataCollectionOf(SocialMediaLinkData::class)]
        public DataCollection $social_media_links,
        public array $certifications
    ) {}
}
