<?php

declare(strict_types=1);

use App\Models\OrganizationPage;
use App\Models\Vendor;
use Illuminate\Database\QueryException;

it('serializes organization page fields', function (): void {
    $page = OrganizationPage::query()->singleton()->firstOrFail();

    expect($page->toArray())->toMatchArray([
        'id'                          => $page->id,
        'vendor_id'                   => null,
        'hero_title'                  => null,
        'hero_description'            => null,
        'ims_portal_url'              => null,
        'request_section_title'       => null,
        'request_section_explanation' => null,
        'faqs'                        => null,
        'hero_image_url'              => null,
        'educational_calendar_url'    => null,
    ]);
});

it('belongs to a vendor', function (): void {
    $vendor = Vendor::factory()->create();
    $page   = OrganizationPage::query()->singleton()->firstOrFail();
    $page->update(['vendor_id' => $vendor->id]);

    expect($page->vendor)
        ->toBeInstanceOf(Vendor::class)
        ->id->toBe($vendor->id);
});

it('rejects a second organization page at the database boundary', function (): void {
    expect(fn (): OrganizationPage => OrganizationPage::query()->create())
        ->toThrow(QueryException::class);
});
