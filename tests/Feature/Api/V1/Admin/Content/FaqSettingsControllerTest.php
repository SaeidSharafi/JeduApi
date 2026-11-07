<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Setting;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns the existing FAQ content when the setting has not been saved', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY]);

    $this->getJson(route('api.v1.admin.settings.faq.show'))
        ->assertOk()
        ->assertJsonCount(3, 'data.categories')
        ->assertJsonPath('data.categories.0.items.0.question', 'چگونه در دوره‌های جهاد دانشگاهی قزوین ثبت‌نام کنم؟');
    $this->getJson(route('api.v1.shop.faq.show'))->assertJsonCount(3, 'data.categories');
});

it('replaces FAQs and returns sanitized answers in order to the shop', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE, PermissionEnum::SETTING_VIEW_ANY]);
    $expected = [
        ['title' => 'Registration', 'items' => [
            ['question' => 'First?', 'answer' => 'One <strong>yes</strong><br />Next'],
            ['question' => 'Second?', 'answer' => 'Two'],
        ]],
        ['title' => 'Support', 'items' => [
            ['question' => 'Third?', 'answer' => '<em>Help</em>'],
        ]],
    ];

    $this->putJson(route('api.v1.admin.settings.faq.update'), ['categories' => [
        ['title' => 'Registration', 'items' => [
            ['question' => 'First?', 'answer' => '<p>One <strong>yes</strong><script>alert(1)</script></p><p>Next</p>'],
            ['question' => 'Second?', 'answer' => '<p>Two</p>'],
        ]],
        ['title' => 'Support', 'items' => [
            ['question' => 'Third?', 'answer' => '<em>Help</em><script>alert(1)</script>'],
        ]],
    ]])->assertOk()->assertJsonPath('data.categories', $expected);

    expect(Setting::where('key', 'faq')->firstOrFail()->value['categories'])->toBe($expected);
    $this->getJson(route('api.v1.admin.settings.faq.show'))->assertJsonPath('data.categories', $expected);
    $this->getJson(route('api.v1.shop.faq.show'))->assertJsonPath('data.categories', $expected);

    $this->putJson(route('api.v1.admin.settings.faq.update'), ['categories' => []])->assertOk();
    $this->getJson(route('api.v1.shop.faq.show'))->assertJsonPath('data.categories', []);
});

it('rejects invalid FAQ items', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_UPDATE]);

    $this->putJson(route('api.v1.admin.settings.faq.update'), ['categories' => [['title' => '', 'items' => [['question' => '', 'answer' => '']]]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['categories.0.title', 'categories.0.items.0.question', 'categories.0.items.0.answer']);
});

it('returns no categories for a previously saved flat FAQ setting', function (): void {
    app(App\Services\SettingsService::class)->set(App\Enums\System\SettingKeyEnum::FAQ, [
        'items' => [['question' => 'Old?', 'answer' => 'Old answer']],
    ], 'json', 'cms');

    $this->getJson(route('api.v1.shop.faq.show'))->assertOk()->assertJsonPath('data.categories', []);
});

it('requires settings permissions to read and update FAQ settings', function (): void {
    $this->authorized_user();

    $this->getJson(route('api.v1.admin.settings.faq.show'))->assertForbidden();
    $this->putJson(route('api.v1.admin.settings.faq.update'), ['categories' => []])->assertForbidden();
});
