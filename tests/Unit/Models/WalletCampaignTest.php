<?php

declare(strict_types=1);

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\WalletCampaign\AllocationStatusEnum;
use App\Models\WalletCampaign;

it('to array', function () {
    $walletCampaign = WalletCampaign::factory()->create()->fresh();
    expect($walletCampaign->toArray())->toEqual([
        'id'                   => $walletCampaign->id,
        'name'                 => $walletCampaign->name,
        'description'          => $walletCampaign->description,
        'type'                 => $walletCampaign->type->value,
        'is_active'            => $walletCampaign->is_active,
        'amount'               => $walletCampaign->amount,
        'usage_limit_total'    => $walletCampaign->usage_limit_total,
        'usage_limit_per_user' => $walletCampaign->usage_limit_per_user,
        'total_usage_count'    => $walletCampaign->total_usage_count,
        'starts_at'            => $walletCampaign->starts_at?->utc()->toJSON(),
        'ends_at'              => $walletCampaign->ends_at?->utc()->toJSON(),
        'metadata'             => $walletCampaign->metadata,
        'created_at'           => $walletCampaign->created_at?->utc()?->toJSON(),
        'updated_at'           => $walletCampaign->updated_at?->utc()?->toJSON(),
        'created_by'           => $walletCampaign->created_by,
    ]);

});

it('increment usage count', function () {
    $walletCampaign = WalletCampaign::factory()->create([
        'total_usage_count' => 0,
    ])->fresh();
    expect($walletCampaign->total_usage_count)->toBe(0);
    $walletCampaign->incrementUsageCount();
    $walletCampaign = $walletCampaign->fresh();
    expect($walletCampaign->total_usage_count)->toBe(1);
});

it('return transactions relationship', function () {
    $walletCampaign = WalletCampaign::factory()->create();
    $transaction    = App\Models\WalletTransaction::factory()->create([
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id'   => $walletCampaign->id,
    ])->fresh();
    $walletCampaign->load('transactions');
    expect($walletCampaign->transactions->first()->toArray())->toEqual($transaction->toArray());
});

it('check is active attribute', function () {
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at'   => now()->addDay(),
    ])->fresh();
    expect($walletCampaign->isActive())->toBeTrue();

    $walletCampaign = WalletCampaign::factory()->create([
        'is_active' => false,
        'starts_at' => now()->subDay(),
        'ends_at'   => now()->addDay(),
    ])->fresh();
    expect($walletCampaign->isActive())->toBeFalse();
});

it('check isWithinDateRange', function () {
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at'   => now()->addDay(),
    ])->fresh();
    expect($walletCampaign->is_within_date_range)->toBeTrue();
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active' => true,
        'starts_at' => now()->addDay(),
        'ends_at'   => now()->addDays(2),
    ])->fresh();
    expect($walletCampaign->is_within_date_range)->toBeFalse();

    $walletCampaign = WalletCampaign::factory()->create([
        'is_active' => true,
        'starts_at' => now()->subDays(2),
        'ends_at'   => now()->subDay(),
    ])->fresh();
    expect($walletCampaign->is_within_date_range)->toBeFalse();
});
it('check can allocate attribute', function () {
    $user           = App\Models\User::factory()->create()->fresh();
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active'         => true,
        'starts_at'         => now()->subDay(),
        'ends_at'           => now()->addDay(),
        'usage_limit_total' => 2,
        'total_usage_count' => 0,
    ])->fresh();
    expect($walletCampaign->allocationStatus($user))->toBe(AllocationStatusEnum::ELIGIBLE);
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active'         => false,
        'starts_at'         => now()->subDay(),
        'ends_at'           => now()->addDay(),
        'usage_limit_total' => 2,
        'total_usage_count' => 0,
    ])->fresh();
    expect($walletCampaign->allocationStatus($user))->toBe(AllocationStatusEnum::ERROR_INACTIVE);
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active'         => true,
        'starts_at'         => now()->addDay(),
        'ends_at'           => now()->addDays(2),
        'usage_limit_total' => 2,
        'total_usage_count' => 0,
    ])->fresh();
    expect($walletCampaign->allocationStatus($user))->toBe(AllocationStatusEnum::ERROR_EXPIRED);
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active'         => true,
        'starts_at'         => now()->subDay(),
        'ends_at'           => now()->addDay(),
        'usage_limit_total' => 2,
        'total_usage_count' => 2,
    ])->fresh();
    expect($walletCampaign->allocationStatus($user))->toBe(AllocationStatusEnum::ERROR_TOTAL_LIMIT_REACHED);
    $walletCampaign = WalletCampaign::factory()->create([
        'is_active'            => true,
        'starts_at'            => now()->subDay(),
        'ends_at'              => now()->addDay(),
        'usage_limit_per_user' => 1,
    ])->fresh();
    // Simulate that the user has already used the campaign once
    App\Models\WalletTransaction::factory()->create([
        'user_id'     => $user->id,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id'   => $walletCampaign->id,
    ])->fresh();
    expect($walletCampaign->allocationStatus($user))->toBe(AllocationStatusEnum::ERROR_USER_LIMIT_REACHED);

});

it('check remaining usage count attribute', function () {
    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => 5,
        'total_usage_count' => 2,
    ])->fresh();
    expect($walletCampaign->remainingUsageCount)->toBe(3);

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => null,
        'total_usage_count' => 2,
    ])->fresh();
    expect($walletCampaign->remainingUsageCount)->toBeNull();

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => 2,
        'total_usage_count' => 5,
    ])->fresh();
    expect($walletCampaign->remainingUsageCount)->toBe(0);
});

it('check hasReachedTotalLimit method', function () {
    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => 5,
        'total_usage_count' => 2,
    ])->fresh();
    expect($walletCampaign->hasReachedTotalLimit())->toBeFalse();

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => null,
        'total_usage_count' => 2,
    ])->fresh();
    expect($walletCampaign->hasReachedTotalLimit())->toBeFalse();

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => 2,
        'total_usage_count' => 5,
    ])->fresh();
    expect($walletCampaign->hasReachedTotalLimit())->toBeTrue();
});

it('check hasReachedUserLimit method', function () {
    $user           = App\Models\User::factory()->create()->fresh();
    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_per_user' => 3,
    ])->fresh();
    expect($walletCampaign->hasReachedUserLimit($user))->toBeFalse();

    // Simulate that the user has already used the campaign twice
    App\Models\WalletTransaction::factory()->count(2)->create([
        'user_id'     => $user->id,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id'   => $walletCampaign->id,
    ])->each->fresh();
    expect($walletCampaign->hasReachedUserLimit($user))->toBeFalse();

    // Simulate that the user has already used the campaign three times
    App\Models\WalletTransaction::factory()->create([
        'user_id'     => $user->id,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id'   => $walletCampaign->id,
    ])->fresh();
    expect($walletCampaign->hasReachedUserLimit($user))->toBeTrue();

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_per_user' => null,
    ])->fresh();
    expect($walletCampaign->hasReachedUserLimit($user))->toBeFalse();
});

it('get remaining usage count', function () {
    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => 10,
        'total_usage_count' => 4,
    ])->fresh();
    expect($walletCampaign->remainingUsageCount)->toBe(6);

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => null,
        'total_usage_count' => 4,
    ])->fresh();
    expect($walletCampaign->remainingUsageCount)->toBeNull();

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_total' => 5,
        'total_usage_count' => 7,
    ])->fresh();
    expect($walletCampaign->remainingUsageCount)->toBe(0);
});

it('get user remaining usage count', function () {
    $user           = App\Models\User::factory()->create()->fresh();
    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_per_user' => 5,
    ])->fresh();
    expect($walletCampaign->getUserRemainingUsageCount($user))->toBe(5);

    // Simulate that the user has already used the campaign twice
    App\Models\WalletTransaction::factory()->count(2)->create([
        'user_id'     => $user->id,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id'   => $walletCampaign->id,
    ])->each->fresh();
    expect($walletCampaign->getUserRemainingUsageCount($user))->toBe(3);

    // Simulate that the user has already used the campaign five times
    App\Models\WalletTransaction::factory()->count(3)->create([
        'user_id'     => $user->id,
        'source_type' => TransactionSourceEnum::CAMPAIGN,
        'source_id'   => $walletCampaign->id,
    ])->each->fresh();
    expect($walletCampaign->getUserRemainingUsageCount($user))->toBe(0);

    $walletCampaign = WalletCampaign::factory()->create([
        'usage_limit_per_user' => null,
    ])->fresh();
    expect($walletCampaign->getUserRemainingUsageCount($user))->toBeNull();
});
