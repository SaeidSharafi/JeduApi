<?php

declare(strict_types=1);

namespace App\Data\Shop\Site;

use App\Data\Admin\Settings\SocialMediaLinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class FooterData extends Data
{
    /**
     * Create a FooterData instance.
     *
     * @param string|null $logo_url Optional URL to the footer logo image.
     * @param string|null $logo_alt Optional alt text for the logo.
     * @param string $caption Required caption or tagline shown in the footer.
     * @param string $support_link URL to the support or help page.
     * @param string $support_email_address Support contact email address.
     * @param array $addresses List of address entries (formatted arrays or strings) to display.
     * @param array $categories List of category items to show in the footer.
     * @param array $main_links List of primary footer links (label => url or structured items).
     * @param DataCollection $social_media_links Collection of SocialMediaLinkData items for social icons.
     * @param array $certifications List of certifications or badges to display.
     */
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
