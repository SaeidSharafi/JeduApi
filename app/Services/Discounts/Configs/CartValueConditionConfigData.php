<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use App\Data\Casts\MathOperatorCast;
use App\Enums\Operators\MathOperatorEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class CartValueConditionConfigData extends Data
{
    public function __construct(
        #[WithCast(MathOperatorCast::class)]
        public MathOperatorEnum $operator,
        public int $value,      // The value to compare against
        /**
         * Measurement-only choice: measure the customer's whole commitment
         * (deposit lines counted at their paid deposit) instead of only the
         * lines being paid in full. Never changes what gets discounted.
         *
         * Optional on purpose: the key was renamed from `include_prepayments`,
         * and a stored configuration (or a client) that omits it means the
         * narrower full-payment measurement — the same meaning the old
         * `false` had. Requiring it here would turn an omitted key into an
         * uncaught `CannotCreateData` on the pricing path.
         */
        public bool $measure_whole_commitment = false,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'operator'                 => ['required', Rule::in(MathOperatorEnum::acceptedValues())],
            'value'                    => ['required', 'integer', 'min:0'],
            'measure_whole_commitment' => ['boolean'],
        ];
    }
}
