<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class RegistrationClosingSoonData extends Data
{
    public function __construct(
        public int $days
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1'],
        ];
    }
}
