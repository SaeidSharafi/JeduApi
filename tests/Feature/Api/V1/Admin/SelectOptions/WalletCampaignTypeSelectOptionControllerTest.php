<?php

declare(strict_types=1);

use App\Enums\WalletCampaign\CampaignTypeEnum;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns every wallet campaign type with label and description', function (): void {
    $this->authorized_user();

    $response = $this->getJson(route('api.v1.admin.select-option.wallet-campaign-types'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'value',
                    'label',
                    'description',
                ],
            ],
        ]);

    foreach (CampaignTypeEnum::cases() as $type) {
        $response->assertJsonFragment([
            'value'       => $type->value,
            'label'       => $type->translate(),
            'description' => $type->getDescription(),
        ]);
    }
});

it('requires staff authentication', function (): void {
    $this->getJson(route('api.v1.admin.select-option.wallet-campaign-types'))
        ->assertUnauthorized();
});
