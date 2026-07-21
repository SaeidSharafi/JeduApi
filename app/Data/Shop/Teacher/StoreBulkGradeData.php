<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class StoreBulkGradeData extends Data
{
    public function __construct(
        public array $enrolments
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        if (!empty($properties['enrolments']) && is_array($properties['enrolments'])) {
            foreach ($properties['enrolments'] as $key => $enrolment) {
                if (isset($enrolment['grades'])) {
                    $properties['enrolments'][$key]['grades'] = collect($enrolment['grades'])
                        ->reject(fn ($value, $k) => str_starts_with((string)$k, '_'))
                        ->toArray();
                }
            }
        }
        return $properties;
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'enrolments'                 => ['array', 'required'],
            'enrolments.*.id'            => ['required', 'integer'],
            'enrolments.*.grades'        => ['required', 'array'],
            'enrolments.*.grades.*'      => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
