<?php

declare(strict_types=1);

namespace App\Actions\Admin\ProductDeliveryOption;

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;

final readonly class GetDeliveryDetailsValidationRulesAction
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>|null  $detailsData
     * @return array<string, mixed>
     */
    public function handle(
        ?string $fulfillmentType,
        ?string $deliveryMethodString,
        ?array $detailsData,
        string $prefix = 'details'
    ): array {

        // @codeCoverageIgnoreStart
        $isGeneratingScribeDocs = app()->runningInConsole()
            && isset($_SERVER['argv'])
            && (
                in_array('scribe:generate', $_SERVER['argv'])
                || in_array('scribe:setup', $_SERVER['argv'])
            );
        if ($isGeneratingScribeDocs) {
            $allDetailsRules    = [];
            $detailsRulesAction = app(self::class);

            foreach (FulfillmentTypeEnum::cases() as $fulfillmentType) {
                foreach (DeliveryMethodEnum::cases() as $deliveryMethod) {
                    $rules = [];
                    if ($fulfillmentType->hasDeliveryMethod($deliveryMethod)) {
                        $dtoClass = $deliveryMethod->getDetailsDtoClass();
                        foreach ($dtoClass::getValidationRules($detailsData ?? []) as $key => $rule) {
                            $newKey         = $prefix ? "{$prefix}.{$key}" : $key;
                            $rules[$newKey] = $rule;
                        }
                    }
                    $allDetailsRules = array_merge($allDetailsRules, $rules);
                }
            }

            return $allDetailsRules;
        }
        // @codeCoverageIgnoreEnd

        $fulfillmentType = FulfillmentTypeEnum::tryFrom($fulfillmentType ?? '');
        $deliveryMethod  = DeliveryMethodEnum::tryFrom($deliveryMethodString ?? '');
        if (! $deliveryMethod || ! $fulfillmentType) {
            return [];
        }
        if (! $fulfillmentType->hasDeliveryMethod($deliveryMethod)) {
            return [];
        }
        $dtoClass = $deliveryMethod->getDetailsDtoClass();

        $rules = [];
        foreach ($dtoClass::getValidationRules($detailsData ?? []) as $key => $rule) {
            $newKey         = $prefix ? "{$prefix}.{$key}" : $key;
            $rules[$newKey] = $rule;
        }
        $keys             = array_keys($dtoClass::getValidationRules($detailsData ?? []));
        $keys             = array_merge(['ims_course_code','start_date','schedule_days','duration'], $keys);
        $rules['details'] = ['required', 'array:'.implode(',', $keys)];

        return $rules;

    }
}
