<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ContactInfoData extends Data
{
    public function __construct(
        #[DataCollectionOf(AddressData::class)]
        public DataCollection $addresses,
        public string $working_hours,
        public string $support_email,
        #[DataCollectionOf(SocialMediaLinkData::class)]
        public DataCollection $social_media_links,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'addresses'                     => ['required', 'array', 'min:1'],
            'addresses.*'                   => ['required', 'array'],
            'addresses.*.name'              => ['required', 'string', 'max:255'],
            'addresses.*.address'           => ['required', 'string', 'max:500'],
            'addresses.*.location_url'      => ['required', 'url'],
            'addresses.*.phone'             => ['required', 'string', 'max:20'],
            'working_hours'                 => ['required', 'string', 'max:255'],
            'support_email'                 => ['required', 'email', 'max:255'],
            'social_media_links'            => ['required', 'array', 'min:1'],
            'social_media_links.*'          => ['required', 'array'],
            'social_media_links.*.platform' => ['required', 'string', 'max:50'],
            'social_media_links.*.link'     => ['required', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(...$args): array
    {
        return [
            'addresses'          => __('validation.attributes.contact_info.addresses'),
            'working_hours'      => __('validation.attributes.contact_info.working_hours'),
            'support_email'      => __('validation.attributes.contact_info.support_email'),
            'social_media_links' => __('validation.attributes.contact_info.social_media_links'),
        ];
    }

    /**
     * Get default contact info data for seeding.
     */
    public static function getDefaults(): array
    {
        return [
            'addresses'          => [
                [
                    'name'         => 'دفتر مرکزی',
                    'address'      => 'تهران، خیابان آزادی، پلاک ۱۲۳',
                    'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                    'phone'        => '۰۲۱-۱۲۳۴۵۶۷۸',
                ],
            ],
            'working_hours'      => 'شنبه تا چهارشنبه، ۹ صبح تا ۵ بعدازظهر',
            'support_email'      => 'info@jedu.ir',
            'social_media_links' => [
                [
                    'platform' => 'instagram',
                    'link'     => 'https://instagram.com/jedushop',
                ],
                [
                    'platform' => 'linkedin',
                    'link'     => 'https://linkedin.com/company/jedushop',
                ],
            ],
        ];
    }
}
