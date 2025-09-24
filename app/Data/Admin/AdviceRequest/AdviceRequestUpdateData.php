<?php

declare(strict_types=1);

namespace App\Data\Admin\AdviceRequest;

use App\Enums\AdviceRequestStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AdviceRequestUpdateData extends Data
{
    public function __construct(
        public string $status,
        public ?string $note = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(AdviceRequestStatusEnum::class)],
            'note'   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
