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
    ) {}

    public static function rules(?ValidationContext $context = null): array
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
            'addresses' => [
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

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'addresses.*.name.required'              => __('validation.custom.contact_info.addresses.name.required'),
            'addresses.*.address.required'           => __('validation.custom.contact_info.addresses.address.required'),
            'addresses.*.location_url.required'      => __('validation.custom.contact_info.addresses.location_url.required'),
            'addresses.*.location_url.url'           => __('validation.custom.contact_info.addresses.location_url.url'),
            'addresses.*.phone.required'             => __('validation.custom.contact_info.addresses.phone.required'),
            'social_media_links.*.platform.required' => __('validation.custom.contact_info.social_media_links.platform.required'),
            'social_media_links.*.link.required'     => __('validation.custom.contact_info.social_media_links.link.required'),
            'social_media_links.*.link.url'          => __('validation.custom.contact_info.social_media_links.link.url'),
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'addresses' => [
                'description' => 'Array of address objects.',
                'example'     => [
                    [
                        'name'         => 'دفتر مرکزی',
                        'address'      => 'تهران، خیابان آزادی، پلاک ۱۲۳',
                        'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                        'phone'        => '۰۲۱-۱۲۳۴۵۶۷۸',
                    ],
                ],
            ],
            'addresses.*.name' => [
                'description' => 'Name of the address.',
                'example'     => 'دفتر مرکزی',
            ],
            'addresses.*.address' => [
                'description' => 'Physical address.',
                'example'     => 'تهران، خیابان آزادی، پلاک ۱۲۳',
            ],
            'addresses.*.location_url' => [
                'description' => 'Google Maps URL for the address.',
                'example'     => 'https://maps.example.com/?q=35.6892,51.3890',
            ],
            'addresses.*.phone' => [
                'description' => 'Phone number for the address.',
                'example'     => '۰۲۱-۱۲۳۴۵۶۷۸',
            ],
            'working_hours' => [
                'description' => 'Working hours for support.',
                'example'     => 'شنبه تا چهارشنبه، ۹ صبح تا ۵ بعدازظهر',
            ],
            'support_email' => [
                'description' => 'Support email address.',
                'example'     => 'info@jedu.ir',
            ],
            'social_media_links' => [
                'description' => 'Array of social media link objects.',
                'example'     => [
                    [
                        'platform' => 'instagram',
                        'link'     => 'https://instagram.com/jedushop',
                    ],
                ],
            ],
            'social_media_links.*.platform' => [
                'description' => 'Social media platform name.',
                'example'     => 'instagram',
            ],
            'social_media_links.*.link' => [
                'description' => 'URL for the social media platform.',
                'example'     => 'https://instagram.com/jedushop',
            ],
        ];
    }
}
