<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddressData extends Data
{
    public function __construct(
        public string $name,
        public string $address,
        public string $location_url,
        public string $phone,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(...$args): array
    {
        return [
            'name'                => __('validation.attributes.address_info.name'),
            'address'             => __('validation.attributes.address_info.address'),
            'map_coordinates.lat' => __('validation.attributes.address_info.latitude'),
            'map_coordinates.lng' => __('validation.attributes.address_info.longitude'),
            'phone'               => __('validation.attributes.address_info.phone'),
        ];
    }
}
