<?php

declare(strict_types=1);

namespace App\Data\Shop\Cart;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ApplyCouponData extends Data
{
    public function __construct(
        public string $coupon_code,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'coupon_code' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'coupon_code' => [
                'description' => 'The coupon code to apply to the cart.',
                'example'     => 'SUMMER2024',
            ],
        ];
    }
}
