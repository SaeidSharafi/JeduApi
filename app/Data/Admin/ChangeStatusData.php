<?php

namespace App\Data\Admin;

use App\Enums\PublicationStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class ChangeStatusData extends Data
{
    public function __construct(
        public string $status
    )
    {
    }

    public static function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
        ];
    }
}
