<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class VendorIsData extends Data
{
    public function __construct(
        public array $vendor_ids
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'vendor_ids'   => ['required', 'array'],
            'vendor_ids.*' => ['integer', 'exists:vendors,id'],
        ];
    }
}
