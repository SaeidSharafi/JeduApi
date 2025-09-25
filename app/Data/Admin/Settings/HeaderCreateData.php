<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class HeaderCreateData extends Data
{
    public function __construct(
        public ?int $logo,
        public array $navigation_links,
        public string $contact_phone,
        public string $contact_email
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'logo'                     => ['nullable', 'integer', 'exists:media,id'],
            'navigation_links'         => ['required', 'array'],
            'navigation_links.*.title' => ['required', 'string', 'max:255'],
            'navigation_links.*.url'   => ['required', 'string', 'max:255'],
            'navigation_links.*.order' => ['required', 'integer:', 'min:0'],
            'contact_phone'            => ['required', 'string', 'max:32'],
            'contact_email'            => ['required', 'string', 'email', 'max:255'],
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
            'navigation_links' => [
                'description' => 'Array of navigation links.',
                'example'     => [
                    ['title' => 'Home', 'url' => '/', 'order' => 1],
                    ['title' => 'Courses', 'url' => '/courses', 'order' => 2],
                ],
            ],
            'navigation_links.*.title' => [
                'description' => 'Title of the navigation link.',
                'example'     => 'Home',
            ],
            'navigation_links.*.url' => [
                'description' => 'URL of the navigation link.',
                'example'     => '/',
            ],
            'navigation_links.*.order' => [
                'description' => 'Order of the navigation link.',
                'example'     => 1,
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
