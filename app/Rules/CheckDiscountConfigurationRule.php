<?php

namespace App\Rules;

use App\Enums\Order\DiscountTypeEnum;
use App\Services\Discounts\DiscountMetadataService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class CheckDiscountConfigurationRule implements ValidationRule, DataAwareRule
{
    private array $data = [];

    public function __construct(private readonly DiscountMetadataService $service)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || empty($value)) {
            return;
        }
        if (!data_get($this->data, 'type')){
            return;
        }
        $hasCondition = false;
        $hasAction = false;
        foreach ($value as $rule) {
            if (!is_array($rule) || !key_exists('type', $rule) || !key_exists('handler', $rule)
                || !key_exists('configuration', $rule)
            ) {
                $fail(__('discount.validation.missing_required_keys', ['attribute' => $attribute]));
                return;
            }
            if (data_get($rule, 'type') === 'condition') {
                $hasCondition = true;
            }
            if (data_get($rule, 'type') === 'action') {
                $hasAction = true;
            }
            $type = data_get($this->data, 'type');
            $type = DiscountTypeEnum::tryFrom($type);
            if (!$type) {
                return;
            }
            $configurationClass = $this->service->getConfigurationClass($rule['handler'], $rule['type'],$type);
            if (!$configurationClass) {
                $fail(__('discount.validation.handler_not_recognized', ['handler' => $rule['handler']]));
                return;
            }
            // get array of validation rules from the configuration class
            $valdiations = $configurationClass::rules();

            $validator = validator($rule['configuration'], $valdiations);
            if ($validator->fails()) {
                $fail(__('discount.validation.configuration_invalid', [
                    'handler' => $rule['handler'],
                    'errors' => implode(', ', $validator->errors()->all())
                ]));
                return;
            }
        }
        $hasCopoun = data_get($this->data, 'type') === 'cart_checkout'
            && data_get($this->data, 'coupons')
            && is_array(data_get($this->data, 'coupons'))
            && count(data_get($this->data, 'coupons')) > 0;

        if (!$hasCondition && !$hasCopoun) {
            $fail(__('discount.validation.condition_required', ['attribute' => $attribute]));
            return;
        }
        if (!$hasAction) {
            $fail(__('discount.validation.action_required', ['attribute' => $attribute]));
            return;
        }

    }

    public function setData(array $data)
    {
        $this->data = $data;
    }
}
