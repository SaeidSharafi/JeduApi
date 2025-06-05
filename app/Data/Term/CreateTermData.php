<?php

declare(strict_types=1);

namespace App\Data\Term;

use App\Enums\TermStatusEnum;
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
        public ?string $start_date,
        public ?string $end_date,
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'status'        => ['nullable', Rule::enum(TermStatusEnum::class)],
            'academic_year' => ['nullable', 'string', 'max:255'],
            'start_date'    => ['nullable', 'date'],
            'end_date'      => ['nullable', 'date'],
        ];
    }
}
