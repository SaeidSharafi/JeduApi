<?php

namespace App\Rules;

use App\Models\DigitalAsset;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DigitalAssetIsAttachableRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (!is_array($value) || empty($value)) {
            $fail(__('validation.array', [
                'attribute' => $attribute,
            ]));
            return;
        }

        foreach ($value as $assetId) {
            if (!is_int($assetId) || $assetId <= 0) {
                $fail(__('validation.array_of_integer', [
                    'attribute' => $attribute,
                ]));

                return;
            }
        }
        $attachableAssets = DigitalAsset::query()
            ->whereIn('id', $value)
            ->where('is_attachable_to_course', true)
            ->pluck('id')
            ->toArray();

        if (count($attachableAssets) !== count($value)) {
            $fail(__('validation.digital_asset.is_not_attachable', [
                'attribute' => $attribute,
            ]));
        }
    }
}
