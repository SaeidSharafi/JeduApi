<?php

declare(strict_types=1);

namespace App\Data\Shop\Organization;

use App\Data\Shop\Product\ProductCardData;
use App\Models\OrganizationPage;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class OrganizationPageData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $faqs
     * @param  array<int, ProductCardData>  $recent_courses
     */
    public function __construct(
        public ?OrganizationDepartmentData $vendor,
        public ?string $hero_title,
        public ?string $hero_description,
        public ?string $ims_portal_url,
        public ?string $request_section_title,
        public ?string $request_section_explanation,
        public array $faqs,
        public ?string $hero_image_url,
        public ?string $educational_calendar_url,
        public array $recent_courses,
    ) {}

    /**
     * @param  Collection<int, ProductCardData>  $recentCourses
     */
    public static function fromModel(OrganizationPage $page, Collection $recentCourses): self
    {
        $page->loadMissing('vendor');

        return new self(
            vendor: $page->vendor ? OrganizationDepartmentData::fromModel($page->vendor) : null,
            hero_title: $page->hero_title,
            hero_description: $page->hero_description,
            ims_portal_url: $page->ims_portal_url,
            request_section_title: $page->request_section_title,
            request_section_explanation: $page->request_section_explanation,
            faqs: $page->faqs ?? [],
            hero_image_url: $page->hero_image_url,
            educational_calendar_url: $page->educational_calendar_url,
            recent_courses: $recentCourses->all(),
        );
    }
}
