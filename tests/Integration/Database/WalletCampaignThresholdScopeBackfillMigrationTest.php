<?php

declare(strict_types=1);

use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Enums\WalletCampaign\ThresholdScopeEnum;
use App\Models\WalletCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds threshold_scope column with index', function (): void {
    expect(Schema::hasColumn('wallet_campaigns', 'threshold_scope'))->toBeTrue();
    expect(Schema::hasIndex('wallet_campaigns', 'wallet_campaigns_threshold_scope_index'))->toBeTrue();
});

it('backfills windowed scope for campaigns with both dates', function (): void {
    $campaign = WalletCampaign::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at'   => now()->addDay(),
    ]);

    $migration = require database_path('migrations/2026_08_16_000002_add_threshold_scope_to_wallet_campaigns_table.php');
    $migration->down();
    $migration->up();

    $campaign->refresh();

    expect($campaign->threshold_scope)->toBe(ThresholdScopeEnum::WINDOWED);
});

it('backfills lifetime scope and clears stray dates for dateless campaigns', function (): void {
    $campaign = WalletCampaign::factory()->create([
        'starts_at' => now()->subDay(),
        'ends_at'   => null,
    ]);

    $migration = require database_path('migrations/2026_08_16_000002_add_threshold_scope_to_wallet_campaigns_table.php');
    $migration->down();
    $migration->up();

    $campaign->refresh();

    expect($campaign->threshold_scope)->toBe(ThresholdScopeEnum::LIFETIME);
    expect($campaign->starts_at)->toBeNull();
    expect($campaign->ends_at)->toBeNull();
});

it('defaults new rows to lifetime scope', function (): void {
    $id = DB::table('wallet_campaigns')->insertGetId([
        'name'       => 'No Scope Provided',
        'type'       => CampaignTypeEnum::MANUAL_ALLOCATION->value,
        'amount'     => 10000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $scope = DB::table('wallet_campaigns')->where('id', $id)->value('threshold_scope');

    expect($scope)->toBe(ThresholdScopeEnum::LIFETIME->value);
});
