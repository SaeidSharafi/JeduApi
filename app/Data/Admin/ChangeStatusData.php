<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Enums\Content\PublicationStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class ChangeStatusData extends Data
{
    public function __construct(
        public string $status
    ) {}

    public static function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(PublicationStatusEnum::class)],
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
            'status' => [
                'description' => 'Publication status value.',
                'example'     => PublicationStatusEnum::PUBLISHED->value,
            ],
        ];
    }
}
