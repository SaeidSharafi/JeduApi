<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Data\Admin\MediaData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class FooterData extends Data
{
    public function __construct(
        public ?MediaData $logo,
        public string $caption,
        public string $support_link,
        public string $support_email_address,
        public array $addresses,
        public array $categories,
        public array $main_links,
        #[DataCollectionOf(SocialMediaLinkData::class)]
        public DataCollection $social_media_links,
        public array $certifications // array of HTML strings
    ) {}

    /**
     * Return the default footer configuration as an associative array.
     *
     * The array contains the following keys:
     * - `logo`: nullable MediaData placeholder for the footer logo.
     * - `logo_url`: nullable string URL for the logo.
     * - `logo_alt`: string alt text for the logo.
     * - `caption`: short caption shown in the footer.
     * - `support_link`: URL to the support/contact page.
     * - `support_email_address`: support email address.
     * - `addresses`: array of contact addresses (sourced from ContactInfoData::getDefaults()).
     * - `categories`: array of footer category titles.
     * - `main_links`: array of link entries, each with `title` and `link`.
     * - `social_media_links`: social links (sourced from ContactInfoData::getDefaults()).
     * - `certifications`: array of certification entries, each with `name`, `image`, and `html`.
     *
     * @return array<string, mixed> Associative array of default footer values.
     */
    public static function getDefaults(): array
    {
        return [
            'logo'                  => null,
            'logo_url'              => null,
            'logo_alt'              => "جهاددانشگاهی قزوین",
            'caption'               => 'شریک شما در آموزش مدرن',
            'support_link'          => '/contact-us',
            'support_email_address' => 'support@jedu.ir',
            'addresses'             => ContactInfoData::getDefaults()['addresses'],
            'categories'            => ['دوره‌ها', 'معماری', 'آموزش صنعتی', 'زبان‌های خارجی'],
            'main_links'            => [
                ['title' => 'درباره ما', 'link' => '/about-us'],
                ['title' => 'وبلاگ', 'link' => '/blog'],
                ['title' => 'تماس با ما', 'link' => '/contact-us'],
                ['title' => 'قوانین', 'link' => '/rules'],
            ],
            'social_media_links' => ContactInfoData::getDefaults()['social_media_links'],
            'certifications'     => [
                ['name' => 'اینماد', 'image' => null, 'html' => ''],
                ['name' => 'ساماندهی', 'image' => null, 'html' => ''],
            ],
        ];
    }
}
