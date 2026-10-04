<?php

declare(strict_types=1);

namespace App\Data\Admin\Organization;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class OrganizationPageUpdateData extends Data
{
    /**
     * @param  array<int, array{question: string, answer: string, is_visible: bool}>  $faqs
     * @param  array{hero: int|null, educational_calendar: int|null}  $media
     */
    public function __construct(
        public int $vendor_id,
        public ?string $hero_title,
        public ?string $hero_description,
        public ?string $ims_portal_url,
        public ?string $request_section_title,
        public ?string $request_section_explanation,
        public array $faqs,
        public array $media,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'vendor_id'                   => ['required', 'integer', 'exists:vendors,id'],
            'hero_title'                  => ['nullable', 'string', 'max:255'],
            'hero_description'            => ['nullable', 'string'],
            'ims_portal_url'              => ['nullable', 'url', 'max:2048'],
            'request_section_title'       => ['nullable', 'string', 'max:255'],
            'request_section_explanation' => ['nullable', 'string'],
            'faqs'                        => ['present', 'array'],
            'faqs.*'                      => ['required', 'array:question,answer,is_visible'],
            'faqs.*.question'             => ['required', 'string'],
            'faqs.*.answer'               => ['required', 'string'],
            'faqs.*.is_visible'           => ['required', 'boolean'],
            'media'                       => ['present', 'array:hero,educational_calendar'],
            'media.hero'                  => ['nullable', 'integer', 'exists:media,id'],
            'media.educational_calendar'  => ['nullable', 'integer', 'exists:media,id'],
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
            'vendor_id' => [
                'description' => 'The Vendor ID for the school department that owns recent Courses.',
                'example'     => 1,
            ],
            'hero_title' => [
                'description' => 'Organization page hero title.',
                'example'     => 'Organization services',
            ],
            'hero_description' => [
                'description' => 'Organization page hero description.',
                'example'     => 'Department information for organizations.',
            ],
            'ims_portal_url' => [
                'description' => 'Optional external IMS organization-management portal URL.',
                'example'     => 'https://ims.example.test/organizations',
            ],
            'request_section_title' => [
                'description' => 'Title for the Organization Training Request introduction.',
                'example'     => 'Request training',
            ],
            'request_section_explanation' => [
                'description' => 'Explanation for the Organization Training Request introduction.',
                'example'     => 'Tell us what your organization needs.',
            ],
            'faqs' => [
                'description' => 'Ordered FAQ objects owned by the Organization page.',
                'example'     => [[
                    'question'   => 'What can we request?',
                    'answer'     => 'A custom training plan.',
                    'is_visible' => true,
                ]],
            ],
            'faqs.*.question' => [
                'description' => 'FAQ question.',
                'example'     => 'What can we request?',
            ],
            'faqs.*.answer' => [
                'description' => 'FAQ answer.',
                'example'     => 'A custom training plan.',
            ],
            'faqs.*.is_visible' => [
                'description' => 'Whether the FAQ is visible to public consumers.',
                'example'     => true,
            ],
            'media' => [
                'description' => 'Mediable attachments keyed by tag.',
                'example'     => [
                    'hero'                 => 10,
                    'educational_calendar' => 11,
                ],
            ],
            'media.hero' => [
                'description' => 'Optional hero media ID.',
                'example'     => 10,
            ],
            'media.educational_calendar' => [
                'description' => 'Optional public educational-calendar media ID.',
                'example'     => 11,
            ],
        ];
    }
}
