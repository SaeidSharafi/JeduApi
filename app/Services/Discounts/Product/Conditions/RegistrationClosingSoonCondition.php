<?php

namespace App\Services\Discounts\Product\Conditions;

use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\RegistrationClosingSoonData;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('registration_closing_soon')]
class RegistrationClosingSoonCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return RegistrationClosingSoonData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        /** @var RegistrationClosingSoonData $configuration */
        if (!$option->registration_end_date) {
            return false;
        }

        $endDate = Carbon::parse($option->registration_end_date);
        $thresholdDate = now()->addDays($configuration->days);

        return $endDate->isFuture() && $endDate->lessThanOrEqualTo($thresholdDate);
    }
}
