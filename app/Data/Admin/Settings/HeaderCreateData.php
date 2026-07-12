<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class HeaderCreateData extends Data
{
    public function __construct(
        public ?int $logo,
        public string $contact_phone,
        public string $contact_email
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'logo'          => ['nullable', 'integer', 'exists:media,id'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
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
            'logo' => [
                'description' => 'Media ID for the logo.',
                'example'     => 301,
            ],
            'contact_phone' => [
                'description' => 'Contact phone number.',
                'example'     => '+982112345678',
            ],
            'contact_email' => [
                'description' => 'Contact email address.',
                'example'     => 'info@jedu.ir',
            ],
        ];
    }
}
