<?php

use App\Enums\Wallet\WalletStatusEnum;
use function Pest\Laravel\getJson;

it('should return wallet information for authenticated user', function () {

    $this->customer();
    /** @var \App\Models\Wallet $wallet */
    $wallet = $this->user->wallet;
    $wallet->update(
        [
            'balance' => 10_000,
            'gift_balance' => 20_000,
            'status' => WalletStatusEnum::ACTIVE,
        ]
    );
    $response = getJson(route('api.v1.shop.wallet.info'));

    expect($response->json('data.balance'))->toBe(10_000)
        ->and($response->json('data.gift_balance'))->toBe(20_000)
        ->and($response->json('data.status.value'))->toBe(WalletStatusEnum::ACTIVE->value)
        ->and($response->json('data.status.label'))->toBe(WalletStatusEnum::ACTIVE->translate());

});
it('it should not return wallet information for unauthenticated user', function () {
    $response = getJson(route('api.v1.shop.wallet.info'));

    $response->assertUnauthorized();
});
