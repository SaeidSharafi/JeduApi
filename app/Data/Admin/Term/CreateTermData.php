<?php

declare(strict_types=1);

namespace App\Data\Admin\Term;

use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\TermStatusEnum;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
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
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $start_date,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $end_date,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        $now = verta()->format('Y-m-d');

        return [
            'name'          => ['required', 'string', 'max:255'],
            'status'        => ['nullable', Rule::enum(TermStatusEnum::class)],
            'academic_year' => ['nullable', 'string', 'max:255'],
            'start_date'    => ['nullable', 'jdate:Y-m-d'],
            'end_date'      => ['nullable', 'jdate:Y-m-d', 'jdate_after:'.request('start_date').',Y-m-d'],
        ];
    }
}
