<?php

declare(strict_types=1);

namespace App\Actions\ProductDeliveryOption;

use App\Enums\DeliveryMethodEnum;
use App\Enums\FulfillmentTypeEnum;

final readonly class GetDeliveryDetailsValidationRulesAction
{
    /**
     * Execute the action.
     */
    public function handle(
        ?string $fulfillmentType,
        ?string $deliveryMethodString,
        ?array $detailsData,
        string $prefix = 'details'
    ): array {
        $fulfillmentType = FulfillmentTypeEnum::tryFrom($fulfillmentType ?? '');
        $deliveryMethod = DeliveryMethodEnum::tryFrom($deliveryMethodString ?? '');
        if (!$deliveryMethod || !$fulfillmentType) {
            return [];
        }
        if (!$fulfillmentType->hasDeliveryMethod($deliveryMethod)) {
            return [];
        }
        $dtoClass = $deliveryMethod->getDetailsDtoClass();

        $rules = [];
        foreach ($dtoClass::getValidationRules($detailsData ?? []) as $key => $rule) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            $rules[$newKey] = $rule;
        }
        $keys = array_keys($dtoClass::getValidationRules($detailsData ?? []));
        $rules['details'] = ['required', 'array:'.implode(',', $keys)];
        return $rules;

    }
}
