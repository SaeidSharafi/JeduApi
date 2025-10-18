<?php

declare(strict_types=1);

namespace App\Data\Shop\Forms;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AdviceRequestCreateData extends Data
{
    public function __construct(
        public string $phone,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'phone' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function bodyParameters(): array
    {
        return [
            'phone' => [
                'description' => 'Phone number of the user requesting consultation.',
                'example'     => '+1234567890',
            ],
        ];
    }
}
