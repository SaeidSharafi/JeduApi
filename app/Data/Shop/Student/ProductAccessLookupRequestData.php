<?php

declare(strict_types=1);

namespace App\Data\Shop\Student;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductAccessLookupRequestData extends Data
{
    public function __construct(
        /** @var array<int, string> */
        public array $option_uuids,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'option_uuids'   => ['required', 'array', 'min:1', 'max:30'],
            'option_uuids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array{description: string, example: mixed}>
     */
    public static function bodyParameters(): array
    {
        return [
            'option_uuids' => [
                'description' => 'Exact product delivery option UUIDs to check. At most 30 unique UUIDs may be submitted.',
                'example'     => ['9f7a9b30-a118-4ed5-9bb3-bca4ed2d3fcb', '3a6f8c11-8931-43ec-9c01-b681739b491a'],
            ],
            'option_uuids.*' => [
                'description' => 'A product delivery option UUID.',
                'example'     => '9f7a9b30-a118-4ed5-9bb3-bca4ed2d3fcb',
            ],
        ];
    }
}
