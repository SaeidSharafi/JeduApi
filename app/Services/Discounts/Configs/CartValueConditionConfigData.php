<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use App\Enums\Operators\MathOperatorEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class CartValueConditionConfigData extends Data
{
    public function __construct(
        public MathOperatorEnum $operator,
        public int $value,      // The value to compare against
        public bool $include_prepayments, // If true, check against the cart's full value
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'operator'            => ['required', Rule::enum(MathOperatorEnum::class)],
            'value'               => ['required', 'integer', 'min:0'],
            'include_prepayments' => ['boolean'],
        ];
    }
}
