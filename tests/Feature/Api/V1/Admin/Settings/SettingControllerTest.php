<?php

declare(strict_types=1);

uses(Tests\AuthTestTrait::class);

it('can get list of settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    App\Models\Setting::factory()->count(3)->create();
    $response = $this->getJson(route('api.v1.admin.settings.index'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    '*' => [
                        'id',
                        'key',
                        'value',
                        'type',
                        'group',
                    ],
                ],
            ],
            'metadata',
        ]);
});
