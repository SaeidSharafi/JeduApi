<?php

declare(strict_types=1);

namespace App\Data\Admin\Term;

use App\Enums\TermStatusEnum;
use App\Helpers\JalaliDateHelper;
use App\Rules\ValidNormalizedJalaliDateRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateTermData extends Data
{
    public function __construct(
        public string $name,
        #[WithCast(EnumCast::class)]
        public ?TermStatusEnum $status,
        public ?string $academic_year,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Carbon $start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Carbon $end_date,
    ) {}

    public static function prepareForPipeline(array $properties): array
    {
        return JalaliDateHelper::toGregorian($properties, [
            'start_date',
            'end_date',
        ]);
    }

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'status'        => ['nullable', Rule::enum(TermStatusEnum::class)],
            'academic_year' => ['nullable', 'string', 'max:255'],
            'start_date'    => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d'],
            'end_date'      => ['bail', 'nullable', new ValidNormalizedJalaliDateRule, 'date_format:Y-m-d', 'after:start_date'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Name of the academic term.',
                'example'     => 'Spring 1402',
            ],
            'status' => [
                'description' => 'Status of the term.',
                'example'     => TermStatusEnum::ACTIVE->value,
            ],
            'academic_year' => [
                'description' => 'Academic year for the term.',
                'example'     => '1402-1403',
            ],
            'start_date' => [
                'description' => 'Start date of the term (Jalali format).',
                'example'     => '1402-01-01',
            ],
            'end_date' => [
                'description' => 'End date of the term (Jalali format).',
                'example'     => '1402-04-31',
            ],
        ];
    }
}
