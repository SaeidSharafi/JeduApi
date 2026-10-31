<?php

declare(strict_types=1);

use App\Actions\Admin\Settings\UpdateSiteIdentityAction;
use App\Data\Admin\Settings\SiteIdentityData;
use App\Enums\PermissionEnum;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

uses(Tests\Support\Traits\AuthTestTrait::class);

mutates(UpdateSiteIdentityAction::class);

it('returns the default identity when nothing has been saved', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);

    $response = $this->getJson(route('api.v1.admin.settings.site-identity.show'));

    $response->assertStatus(200)
        ->assertJsonPath('data.site_title', SiteIdentityData::getDefaults()['site_title'])
        ->assertJsonPath('data.favicon', null)
        ->assertJsonPath('data.favicon_url', null);
});

it('saves the title and the favicon and returns the resolved favicon', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

    Storage::fake('public');
    $favicon = MediaUploader::fromSource(UploadedFile::fake()->image('favicon.png'))
        ->toDisk('public')
        ->upload();

    $response = $this->putJson(route('api.v1.admin.settings.site-identity.update'), [
        'site_title' => '  جهاد دانشگاهی قزوین  ',
        'favicon'    => $favicon->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.site_title', 'جهاد دانشگاهی قزوین')
        ->assertJsonPath('data.favicon.id', $favicon->id)
        ->assertJsonPath('data.favicon_url', $favicon->getUrl());

    $setting = Setting::where('key', 'site_identity')->first();

    expect($setting)->not->toBeNull()
        ->and($setting->value['site_title'])->toBe('جهاد دانشگاهی قزوین')
        ->and($setting->value['favicon'])->toBe($favicon->id)
        ->and($setting->value['favicon_url'])->toBe($favicon->getUrl())
        ->and($setting->media()->wherePivot('tag', 'favicon')->count())->toBe(1);
});

it('clears the stored favicon when none is sent', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

    Storage::fake('public');
    $favicon = MediaUploader::fromSource(UploadedFile::fake()->image('favicon.png'))
        ->toDisk('public')
        ->upload();

    $this->putJson(route('api.v1.admin.settings.site-identity.update'), [
        'site_title' => 'Jedu',
        'favicon'    => $favicon->id,
    ])->assertStatus(200);

    $response = $this->putJson(route('api.v1.admin.settings.site-identity.update'), [
        'site_title' => 'Jedu',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.favicon', null)
        ->assertJsonPath('data.favicon_url', null);

    expect(Setting::where('key', 'site_identity')->first()->media()->count())->toBe(0);
});

it('rejects a favicon that is not an image', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

    Storage::fake('public');
    $document = MediaUploader::fromSource(
        UploadedFile::fake()->create('terms.pdf', 10, 'application/pdf')
    )->toDisk('public')->upload();

    $response = $this->putJson(route('api.v1.admin.settings.site-identity.update'), [
        'site_title' => 'Jedu',
        'favicon'    => $document->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['favicon']);
});

it('validates the payload', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE->value]);

    $this->putJson(route('api.v1.admin.settings.site-identity.update'), [
        'site_title' => '',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['site_title']);

    $this->putJson(route('api.v1.admin.settings.site-identity.update'), [
        'site_title' => 'Jedu',
        'favicon'    => 999999,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['favicon']);
});

it('cannot read or write the identity without auth', function (): void {
    $this->unauthorized_user();

    $this->getJson(route('api.v1.admin.settings.site-identity.show'))->assertStatus(403);
    $this->putJson(route('api.v1.admin.settings.site-identity.update'), [
        'site_title' => 'Jedu',
    ])->assertStatus(403);
});
