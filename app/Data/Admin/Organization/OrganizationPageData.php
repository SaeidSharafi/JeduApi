<?php

declare(strict_types=1);

namespace App\Data\Admin\Organization;

use App\Data\Admin\MediaData;
use App\Models\OrganizationPage;
use Spatie\LaravelData\Data;

final class OrganizationPageData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $faqs
     * @param  array<string, array<int, MediaData>>  $media
     */
    public function __construct(
        public int $id,
        public ?int $vendor_id,
        public ?string $hero_title,
        public ?string $hero_description,
        public ?string $ims_portal_url,
        public ?string $request_section_title,
        public ?string $request_section_explanation,
        public array $faqs,
        public ?string $hero_image_url,
        public ?string $educational_calendar_url,
        public array $media,
    ) {}

    public static function fromModel(OrganizationPage $page): self
    {
        $page->loadMissing('media');

        return new self(
            id: $page->id,
            vendor_id: $page->vendor_id,
            hero_title: $page->hero_title,
            hero_description: $page->hero_description,
            ims_portal_url: $page->ims_portal_url,
            request_section_title: $page->request_section_title,
            request_section_explanation: $page->request_section_explanation,
            faqs: $page->faqs ?? [],
            hero_image_url: $page->hero_image_url,
            educational_calendar_url: $page->educational_calendar_url,
            media: $page->getAllMedia(onlyTags: ['hero', 'educational_calendar']),
        );
    }
}
