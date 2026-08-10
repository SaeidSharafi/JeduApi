<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption;

use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;

final class ProductDeliveryOptionDateNormalizer
{
    /**
     * @var array<string, string>
     */
    private const FIELDS = [
        'featured_price_start_date'     => 'Y-m-d H:i:s',
        'featured_price_end_date'       => 'Y-m-d H:i:s',
        'registration_start_date'       => 'Y-m-d',
        'registration_end_date'         => 'Y-m-d',
        'available_from'                => 'Y-m-d',
        'available_to'                  => 'Y-m-d',
        'details.start_date'            => 'Y-m-d',
        'details.enrollment_start_date' => 'Y-m-d',
        'details.enrollment_end_date'   => 'Y-m-d',
    ];

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function normalize(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, self::FIELDS);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        $validJalaliDate = new ValidNormalizedJalaliDateRule;

        return [
            'featured_price_start_date'     => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d H:i:s'],
            'featured_price_end_date'       => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d H:i:s', 'after:featured_price_start_date'],
            'registration_start_date'       => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d'],
            'registration_end_date'         => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d', 'after:registration_start_date'],
            'available_from'                => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d'],
            'available_to'                  => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d', 'after:available_from'],
            'details.start_date'            => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d'],
            'details.enrollment_start_date' => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d'],
            'details.enrollment_end_date'   => ['bail', 'nullable', $validJalaliDate, 'date_format:Y-m-d', 'after:details.enrollment_start_date'],
        ];
    }
}
