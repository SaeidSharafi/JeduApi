<?php

use App\Actions\Admin\Wallet\AllocateGiftCreditAction;
use App\Enums\PermissionEnum;
use App\Enums\Wallet\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function () {
    $this->user = Staff::factory()->create();
    $this->customer = User::factory()->create();
    $this->campaign = WalletCampaign::factory()->create([
        'name' => 'Welcome Gift Campaign',
        'type' => CampaignTypeEnum::WELCOME_GIFT,
        'amount' => 25000,
        'is_active' => true,
        'usage_limit_total' => 1000,
        'usage_limit_per_user' => 1,
        'total_usage_count' => 0,
        'starts_at' => Carbon::now()->subDay(),
        'ends_at' => Carbon::now()->addMonth(),
        'created_by' => $this->user->id,
    ]);
});

it('successfully allocates gift credit to user', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $requestData = [
        'campaign_id' => $this->campaign->id,
        'user_id' => $this->customer->id,
        'reason' => 'Welcome gift for new user',
        'metadata' => [
            'admin_id' => $this->user->id,
            'source' => 'admin_panel'
        ]
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'wallet' => [
                    'balance',
                    'gift_balance',
                    'status',
                    'user'
                ],
                'user' => [
                    'id',
                    'uuid',
                    'first_name',
                    'last_name',
                    'phone',
                    'phone2',
                    'phone_verified_at',
                    'email',
                    'email_verified_at',
                    'civil_id',
                    'civil_id_type',
                    'date_of_birth',
                    'father_name',
                    'gender',
                    'education_level',
                    'field_of_study',
                    'education_status',
                    'created_at',
                    'updated_at',
                ],
                'type',
                'amount',
                'balance_after',
                'gift_balance_after',
                'source_type',
                'source' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'is_active',
                    'amount',
                    'usage_limit_total',
                    'usage_limit_per_user',
                    'total_usage_count',
                    'starts_at',
                    'ends_at',
                    'metadata',
                    'created_by',
                    'created_at',
                    'updated_at',
                    'remaining_usage_count',
                    'is_within_date_range',
                    'transactions_count',
                ],
                'description',
                'metadata',
                'expires_at',
                'created_at',
            ]
        ]);

    expect($response->json('data.amount'))->toBe(25000);
    expect($response->json('data.user.id'))->toBe($this->customer->id);

    // Verify transaction was created in database
    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $this->customer->id,
        'amount' => 25000,
        'type' => 'gift',
        'source_type' => 'campaign',
        'source_id' => $this->campaign->id,
    ]);

    // Verify campaign usage count was incremented
    expect($this->campaign->fresh()->total_usage_count)->toBe(1);
});

it('returns existing transaction for duplicate allocation (idempotency)', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    // Create existing transaction
    $existingTransaction = WalletTransaction::factory()->create([
        'user_id' => $this->customer->id,
        'type' => 'gift',
        'source_type' => 'campaign',
        'source_id' => $this->campaign->id,
        'amount' => 25000,
    ]);
    $requestData = [
        'user_id' => $this->customer->id,
        'reason' => 'Duplicate allocation attempt'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertUnprocessable()
    ->assertJsonFragment(['message' => __('validation.custom.already_claimed')]);

    expect($this->campaign->fresh()->total_usage_count)->toBe(0);

});

it('validates required fields', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('validates campaign exists', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $requestData = [
        'user_id' => $this->customer->id,
        'reason' => 'Test allocation'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', 99), $requestData);

    $response->assertNotFound();
});

it('validates user exists', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $requestData = [
        'user_id' => 99999, // Non-existent user
        'reason' => 'Test allocation'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertUnprocessable()
        ->assertJsonStructure([
            'message',
        ]);
});

it('rejects allocation when campaign is inactive', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $this->campaign->update(['is_active' => false]);

    $requestData = [
        'user_id' => $this->customer->id,
        'reason' => 'Test allocation'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertUnprocessable()
        ->assertJsonFragment(['message' => __('validation.custom.campaign_not_active')]);
});

it('rejects allocation when campaign is expired', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $this->campaign->update([
        'starts_at' => Carbon::now()->subMonth(),
        'ends_at' => Carbon::now()->subDay(),
    ]);

    $requestData = [
        'campaign_id' => $this->campaign->id,
        'user_id' => $this->customer->id,
        'reason' => 'Test allocation'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertUnprocessable()
        ->assertJsonFragment(['message' => __('validation.custom.campaign_expired')]);
});

it('rejects allocation when total usage limit reached', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $this->campaign->update([
        'usage_limit_total' => 5,
        'total_usage_count' => 5,
    ]);

    $requestData = [
        'campaign_id' => $this->campaign->id,
        'user_id' => $this->customer->id,
        'reason' => 'Test allocation'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertUnprocessable()
        ->assertJsonFragment(['message' => __('validation.custom.usage_limit_reached')]);;
});

it('rejects allocation when user has no wallet', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();

    $requestData = [
        'campaign_id' => $this->campaign->id,
        'user_id' => $userWithoutWallet->id,
        'reason' => 'Test allocation'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertUnprocessable()
        ->assertJsonFragment(['message' => __('validation.custom.wallet_not_found')]);
});

it('handles allocation with optional metadata', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $requestData = [
        'campaign_id' => $this->campaign->id,
        'user_id' => $this->customer->id,
        'reason' => 'Special allocation',
        'metadata' => [
            'promotion_code' => 'WELCOME2023',
            'source_page' => 'admin_dashboard',
            'notes' => 'Manual allocation for VIP user'
        ]
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertOk();

    $transaction = WalletTransaction::where('user_id', $this->customer->id)->first();
    expect($transaction->metadata)->toHaveKey('promotion_code');
    expect($transaction->metadata['promotion_code'])->toBe('WELCOME2023');
});

it('handles allocation without optional reason', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);

    $requestData = [
        'campaign_id' => $this->campaign->id,
        'user_id' => $this->customer->id,
        // No reason provided
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertOk();

    // Should use default localized description
    $transaction = WalletTransaction::where('user_id', $this->customer->id)->first();
    expect($transaction->description)->toContain('Welcome Gift Campaign');
});

it('denies access without permission', function () {
    // Staff doesn't have WALLET_CAMPAIGN_ALLOCATE permission
    $this->unauthorized_user();
    $requestData = [
        'campaign_id' => $this->campaign->id,
        'user_id' => $this->customer->id,
        'reason' => 'Test allocation'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocate-gift-credit', $this->campaign), $requestData);

    $response->assertForbidden();
});
