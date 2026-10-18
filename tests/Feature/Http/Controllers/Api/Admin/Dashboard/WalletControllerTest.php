<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\Wallet\WalletStatusEnum;
use App\Models\User;
use App\Models\Wallet;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

const WALLET_ENDPOINT = '/api/v1/admin/dashboard/wallet';

/**
 * Create a customer, then give the wallet the customer boot-created the requested figures.
 */
function walletHolding(int $balance, int $giftBalance, WalletStatusEnum $status = WalletStatusEnum::ACTIVE): Wallet
{
    $user = User::factory()->create();

    $user->wallet->update([
        'balance'      => $balance,
        'gift_balance' => $giftBalance,
        'status'       => $status,
    ]);

    return $user->wallet->refresh();
}

it('rejects an unauthenticated request', function (): void {
    getJson(WALLET_ENDPOINT)->assertUnauthorized();
});

it('forbids a staff member holding only the dashboard view permission', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    getJson(WALLET_ENDPOINT)->assertForbidden();
});

it('returns the wallet liability envelope for a staff member with the financials permission', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    $response = getJson(WALLET_ENDPOINT);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'normal_balance',
            'gift_balance',
            'total_liability',
            'active_wallets',
        ],
        'metadata' => [
            'generated_at',
            'cached',
            'cache_ttl',
            'timezone',
            'currency',
        ],
    ]);
    $response->assertJsonPath('data.normal_balance', 0);
    $response->assertJsonPath('data.gift_balance', 0);
    $response->assertJsonPath('data.total_liability', 0);
    $response->assertJsonPath('data.active_wallets', 0);
    $response->assertJsonPath('metadata.cached', false);
    $response->assertJsonPath('metadata.cache_ttl', 300);
    $response->assertJsonPath('metadata.timezone', 'Asia/Tehran');
    $response->assertJsonPath('metadata.currency', 'IRR');
});

it('reports the snapshot generated_at as a Jalali timestamp in Tehran time', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $this->travelTo('2025-01-01 12:00:00');

    $response = getJson(WALLET_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('metadata.generated_at', '1403-10-12 12:00:00');
});

it('sums normal and gift balances of active wallets into the liability', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    walletHolding(2_500_000, 400_000);
    walletHolding(1_500_000, 100_000);
    walletHolding(9_999_999, 9_999_999, WalletStatusEnum::SUSPENDED);
    walletHolding(8_888_888, 8_888_888, WalletStatusEnum::CLOSED);

    $response = getJson(WALLET_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.normal_balance', 4_000_000);
    $response->assertJsonPath('data.gift_balance', 500_000);
    $response->assertJsonPath('data.total_liability', 4_500_000);
    $response->assertJsonPath('data.active_wallets', 2);
});

it('returns integer amounts', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    walletHolding(2_500_000, 400_000);

    $response = getJson(WALLET_ENDPOINT);

    $response->assertOk();
    expect($response->json('data.normal_balance'))->toBeInt()
        ->and($response->json('data.gift_balance'))->toBeInt()
        ->and($response->json('data.total_liability'))->toBeInt()
        ->and($response->json('data.active_wallets'))->toBeInt();
});

it('serves the cached snapshot until fresh=1 recomputes it', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $wallet = walletHolding(1_000_000, 200_000);
    $this->travelTo('2025-01-01 12:00:00');

    $first = getJson(WALLET_ENDPOINT);
    $first->assertOk();
    $first->assertJsonPath('data.total_liability', 1_200_000);
    $first->assertJsonPath('metadata.cached', false);
    $first->assertJsonPath('metadata.generated_at', '1403-10-12 12:00:00');

    $wallet->update(['balance' => 7_000_000]);
    $this->travel(1)->minutes();

    $cached = getJson(WALLET_ENDPOINT);
    $cached->assertOk();
    $cached->assertJsonPath('data.total_liability', 1_200_000);
    $cached->assertJsonPath('data.normal_balance', 1_000_000);
    $cached->assertJsonPath('metadata.cached', true);
    $cached->assertJsonPath('metadata.generated_at', '1403-10-12 12:00:00');

    $fresh = getJson(WALLET_ENDPOINT.'?fresh=1');
    $fresh->assertOk();
    $fresh->assertJsonPath('data.total_liability', 7_200_000);
    $fresh->assertJsonPath('data.normal_balance', 7_000_000);
    $fresh->assertJsonPath('metadata.cached', false);
    $fresh->assertJsonPath('metadata.generated_at', '1403-10-12 12:01:00');
});

it('ignores an unknown period instead of failing', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    getJson(WALLET_ENDPOINT.'?period=last-century')->assertOk();
});
