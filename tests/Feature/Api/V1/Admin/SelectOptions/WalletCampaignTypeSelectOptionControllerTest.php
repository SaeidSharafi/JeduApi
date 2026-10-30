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
                    'creatable',
                ],
            ],
        ]);

    foreach (CampaignTypeEnum::cases() as $type) {
        $response->assertJsonFragment([
            'value'       => $type->value,
            'label'       => $type->translate(),
            'description' => $type->getDescription(),
            'creatable'   => $type->isCreatable(),
        ]);
    }
});

it('marks reserved campaign types as not creatable', function (): void {
    $this->authorized_user();

    $response = $this->getJson(route('api.v1.admin.select-option.wallet-campaign-types'));

    $creatable = collect($response->json('data'))->where('creatable', true)->pluck('value');

    expect($creatable)->not->toContain(CampaignTypeEnum::REFERRAL_BONUS->value)
        ->and($creatable)->not->toContain(CampaignTypeEnum::SEASONAL_BONUS->value)
        ->and($creatable)->toContain(CampaignTypeEnum::MANUAL_ALLOCATION->value);
});

it('requires staff authentication', function (): void {
    $this->getJson(route('api.v1.admin.select-option.wallet-campaign-types'))
        ->assertUnauthorized();
});
