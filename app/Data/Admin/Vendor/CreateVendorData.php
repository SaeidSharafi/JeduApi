<?php

declare(strict_types=1);

namespace App\Data\Admin\Vendor;

use App\Rules\IranMobilePhoneRule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateVendorData extends Data
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $phone2,
        public ?string $address,
        public ?string $map_location,
        public ?array $social_links,
        public ?array $theme_options,
        public array $media,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'string', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', new IranMobilePhoneRule()],
            'phone2'        => ['nullable', 'string', new IranMobilePhoneRule()],
            'address'       => ['nullable', 'string', 'max:600'],
            'map_location'  => ['nullable', 'string', 'max:2000'],
            'social_links'  => ['nullable', 'array'],
            'theme_options' => ['nullable', 'array'],
            'media'         => ['present', 'array:logo,favicon'],
            'media.logo'    => ['nullable', 'integer', 'exists:media,id'],
            'media.favicon' => ['nullable', 'integer', 'exists:media,id'],
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
            'name' => [
                'description' => 'Vendor name',
                'example'     => 'My Vendor',
            ],
            'email' => [
                'description' => 'Vendor email',
                'example'     => 'vendor@exanple.com',
            ],
            'phone' => [
                'description' => 'Vendor primary phone number',
                'example'     => '02812345678',
            ],
            'phone2' => [
                'description' => 'Vendor secondary phone number',
                'example'     => '02812345678',
            ],
            'address' => [
                'description' => 'Vendor address',
                'example'     => '123 Main St, Tehran, Iran',
            ],
            'map_location' => [
                'description' => 'Vendor map location (URL or coordinates)',
                'example'     => 'https://maps.google.com/?q=35.6892,51.3890',
            ],
            'social_links' => [
                'description' => 'Array of social media links',
                'example'     => [
                    'facebook'  => 'https://facebook.com/vendor',
                    'twitter'   => 'https://twitter.com/vendor',
                    'instagram' => 'https://instagram.com/vendor',
                ],
            ],
            'theme_options' => [
                'description' => 'Array of theme options for the vendor',
                'example'     => [
                    'color_scheme' => 'dark',
                    'layout'       => 'grid',
                ],
            ],
            'media' => [
                'description' => 'array of associated media ids',
            ],
            'media.logo' => [
                'description' => 'media id for logo',
                'example'     => 1,
            ],
            'media.favicon' => [
                'description' => 'media id for favicon',
                'example'     => 1,
            ],
        ];
    }
}
