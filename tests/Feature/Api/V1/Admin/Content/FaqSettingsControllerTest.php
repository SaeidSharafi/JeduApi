<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Setting;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns the existing FAQ content when the setting has not been saved', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY]);

    $this->getJson(route('api.v1.admin.settings.faq.show'))
        ->assertOk()
        ->assertJsonCount(8, 'data.items')
        ->assertJsonPath('data.items.0.question', 'چگونه در دوره‌های جهاد دانشگاهی قزوین ثبت‌نام کنم؟');
    $this->getJson(route('api.v1.shop.faq.show'))->assertJsonCount(8, 'data.items');
});

it('replaces FAQs and returns sanitized answers in order to the shop', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE, PermissionEnum::SETTING_VIEW_ANY]);
    $expected = [
        ['question' => 'First?', 'answer' => 'One <strong>yes</strong><br />Next'],
        ['question' => 'Second?', 'answer' => 'Two'],
    ];

    $this->putJson(route('api.v1.admin.settings.faq.update'), ['items' => [
        ['question' => 'First?', 'answer' => '<p>One <strong>yes</strong><script>alert(1)</script></p><p>Next</p>'],
        ['question' => 'Second?', 'answer' => '<p>Two</p>'],
    ]])->assertOk()->assertJsonPath('data.items', $expected);

    expect(Setting::where('key', 'faq')->firstOrFail()->value['items'])->toBe($expected);
    $this->getJson(route('api.v1.admin.settings.faq.show'))->assertJsonPath('data.items', $expected);
    $this->getJson(route('api.v1.shop.faq.show'))->assertJsonPath('data.items', $expected);

    $this->putJson(route('api.v1.admin.settings.faq.update'), ['items' => []])->assertOk();
    $this->getJson(route('api.v1.shop.faq.show'))->assertJsonPath('data.items', []);
});

it('rejects invalid FAQ items', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE]);

    $this->putJson(route('api.v1.admin.settings.faq.update'), ['items' => [['question' => '', 'answer' => '']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.question', 'items.0.answer']);
});

it('requires settings permissions to read and update FAQ settings', function (): void {
    $this->authorized_user();

    $this->getJson(route('api.v1.admin.settings.faq.show'))->assertForbidden();
    $this->putJson(route('api.v1.admin.settings.faq.update'), ['items' => []])->assertForbidden();
});
