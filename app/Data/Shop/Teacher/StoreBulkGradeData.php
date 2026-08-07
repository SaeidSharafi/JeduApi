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
                        ->reject(fn ($value, $k): bool => str_starts_with((string)$k, '_'))
                        ->toArray();
                }
            }
        }
        return $properties;
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'enrolments'                 => ['array', 'required'],
            'enrolments.*.id'            => ['required', 'integer'],
            'enrolments.*.grades'        => ['required', 'array'],
            'enrolments.*.grades.*'      => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'enrolments' => [
                'description' => 'array of enrolment objects',
                'example'     => [
                    [
                        'id' => 120,
                        'grades' => [
                            'final' => 100
                        ],
                    ]
                ]
            ],
            'enrolments.*.id' => [
                'description' => 'the IMS enrolment ID.',
                'example'     => 456,
            ],
            'enrolments.*.grades.*'       => [
                'description' => 'An array of grade values. The structure is taken from the get grade response. Example: {midterm: 20, final: 70}',
                'example'     => [
                    [
                        'midterm' => 85,
                        'final'   => 85,
                    ],
                ],
            ],
        ];
    }
}
