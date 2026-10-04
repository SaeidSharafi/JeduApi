<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Enums\System\MorphTypeEnum;
use App\Models\OrganizationPage;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns the initialized organization page to authorized staff', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY]);

    $response = $this->getJson(route('api.v1.admin.landing-pages.organization.show'));

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'vendor_id',
                'hero_title',
                'hero_description',
                'ims_portal_url',
                'request_section_title',
                'request_section_explanation',
                'faqs',
                'hero_image_url',
                'educational_calendar_url',
                'media' => ['hero', 'educational_calendar'],
            ],
            'metadata',
        ])
        ->assertJsonPath('data.vendor_id', null)
        ->assertJsonPath('data.faqs', [])
        ->assertJsonPath('data.hero_image_url', null)
        ->assertJsonPath('data.educational_calendar_url', null)
        ->assertJsonPath('data.media.hero', [])
        ->assertJsonPath('data.media.educational_calendar', []);
});

it('fully replaces page content and mediable attachments', function (): void {
    Storage::fake('public');
    $this->authorized_user([PermissionEnum::SETTING_UPDATE]);

    $firstVendor  = Vendor::factory()->create();
    $secondVendor = Vendor::factory()->create();
    $firstHero    = MediaUploader::fromSource(UploadedFile::fake()->image('first-hero.jpg'))
        ->toDisk('public')
        ->upload();
    $firstCalendar = MediaUploader::fromSource(UploadedFile::fake()->create('first-calendar.pdf', 10, 'application/pdf'))
        ->toDisk('public')
        ->upload();
    $secondHero = MediaUploader::fromSource(UploadedFile::fake()->image('second-hero.jpg'))
        ->toDisk('public')
        ->upload();
    app(CacheStore::class)->put(CacheKey::OrganizationPage, ['limit' => 6], ['stale' => true]);

    $basePayload = [
        'hero_title'                  => 'Organization services',
        'hero_description'            => 'Department description',
        'ims_portal_url'              => 'https://ims.example.test/organizations',
        'request_section_title'       => 'Request training',
        'request_section_explanation' => 'Tell us what your organization needs.',
    ];

    $firstResponse = $this->putJson(route('api.v1.admin.landing-pages.organization.update'), [
        ...$basePayload,
        'vendor_id' => $firstVendor->id,
        'faqs'      => [[
            'question'   => 'First question',
            'answer'     => 'First answer',
            'is_visible' => true,
        ]],
        'media' => [
            'hero'                 => $firstHero->id,
            'educational_calendar' => $firstCalendar->id,
        ],
    ]);

    $firstResponse->assertOk()
        ->assertJsonPath('data.vendor_id', $firstVendor->id)
        ->assertJsonPath('data.faqs.0.question', 'First question')
        ->assertJsonPath('data.hero_image_url', $firstHero->getUrl())
        ->assertJsonPath('data.educational_calendar_url', $firstCalendar->getUrl())
        ->assertJsonPath('data.media.hero.0.id', $firstHero->id)
        ->assertJsonPath('data.media.educational_calendar.0.id', $firstCalendar->id);

    expect(app(CacheStore::class)->get(CacheKey::OrganizationPage, ['limit' => 6]))->toBeNull();

    $secondResponse = $this->putJson(route('api.v1.admin.landing-pages.organization.update'), [
        ...$basePayload,
        'vendor_id' => $secondVendor->id,
        'faqs'      => [],
        'media'     => [
            'hero'                 => $secondHero->id,
            'educational_calendar' => null,
        ],
    ]);

    $secondResponse->assertOk()
        ->assertJsonPath('data.vendor_id', $secondVendor->id)
        ->assertJsonPath('data.faqs', [])
        ->assertJsonPath('data.hero_image_url', $secondHero->getUrl())
        ->assertJsonPath('data.educational_calendar_url', null)
        ->assertJsonPath('data.media.hero.0.id', $secondHero->id)
        ->assertJsonPath('data.media.educational_calendar', []);

    $page = OrganizationPage::query()->firstOrFail();

    expect($page->vendor_id)->toBe($secondVendor->id)
        ->and($page->faqs)->toBe([])
        ->and($page->hero_image_url)->toBe($secondHero->getUrl())
        ->and($page->educational_calendar_url)->toBeNull();

    $this->assertDatabaseMissing('mediables', [
        'mediable_id'   => $page->id,
        'mediable_type' => MorphTypeEnum::ORGANIZATION_PAGE->value,
        'media_id'      => $firstHero->id,
        'tag'           => 'hero',
    ]);
    $this->assertDatabaseMissing('mediables', [
        'mediable_id'   => $page->id,
        'mediable_type' => MorphTypeEnum::ORGANIZATION_PAGE->value,
        'media_id'      => $firstCalendar->id,
        'tag'           => 'educational_calendar',
    ]);
});

it('requires a linked vendor for page settings updates', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE]);

    $response = $this->putJson(route('api.v1.admin.landing-pages.organization.update'), [
        'vendor_id'                   => null,
        'hero_title'                  => null,
        'hero_description'            => null,
        'ims_portal_url'              => null,
        'request_section_title'       => null,
        'request_section_explanation' => null,
        'faqs'                        => [],
        'media'                       => [
            'hero'                 => null,
            'educational_calendar' => null,
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['vendor_id']);
});

it('enforces settings permissions', function (): void {
    $this->authorized_user();

    $response = $this->getJson(route('api.v1.admin.landing-pages.organization.show'));

    $response->assertForbidden();
});

it('blocks deletion of a vendor linked to the organization page', function (): void {
    $vendor = Vendor::factory()->create();
    OrganizationPage::query()->firstOrFail()->update(['vendor_id' => $vendor->id]);
    $this->authorized_user([PermissionEnum::VENDOR_DELETE]);

    $response = $this->deleteJson(route('api.v1.admin.vendors.destroy', ['vendor' => $vendor->id]));

    $response->assertUnprocessable()
        ->assertJsonFragment([
            'message' => __('messages.errors.model_has_relationship_data', [
                'related_model' => getModelLabel(OrganizationPage::class),
            ]),
        ]);
    $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
});
