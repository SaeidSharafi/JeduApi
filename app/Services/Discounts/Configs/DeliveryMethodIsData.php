<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use App\Enums\Product\DeliveryMethodEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class DeliveryMethodIsData extends Data
{
    /**
     * @param  array<int, DeliveryMethodEnum>  $delivery_methods
     */
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
            'delivery_methods.*' => ['required', Rule::enum(DeliveryMethodEnum::class)],
        ];
    }
}
