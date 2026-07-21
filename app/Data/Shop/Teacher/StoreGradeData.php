<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class StoreGradeData extends Data
{
    public function __construct(
        public int $enrolment_id,
        public array $grades,
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        if (!empty($properties['grades'])) {
            $properties['grades'] = collect($properties['grades'])
                ->reject(fn ($value, $key) => str_starts_with((string)$key, '_'))
                ->toArray();
        }
        return $properties;
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'enrolment_id' => ['required', 'integer'],
            'grades'       => ['required', 'array'],
            'grades.*'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
