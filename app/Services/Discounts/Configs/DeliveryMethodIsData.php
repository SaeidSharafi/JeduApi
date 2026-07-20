<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class DeliveryMethodIsData extends Data
{
    public function __construct(
        public array $delivery_methods
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'delivery_methods'   => ['required', 'array'],
            'delivery_methods.*' => ['string'],
        ];
    }
}
