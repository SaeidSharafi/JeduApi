<?php

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
        'name' => 'Referral Bonus Campaign',
        'type' => CampaignTypeEnum::REFERRAL_BONUS,
        'amount' => 30000,
        'is_active' => true,
        'usage_limit_total' => 500,
        'usage_limit_per_user' => 3,
        'total_usage_count' => 0,
        'starts_at' => Carbon::now()->subDay(),
        'ends_at' => Carbon::now()->addMonth(),
        'created_by' => $this->user->id,
    ]);
});

it('successfully processes campaign bonus for user', function () {
    $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $requestData = [

        'user_id' => $this->customer->id,
        'trigger_event' => 'referral_completed',
        'metadata' => [
            'referred_user_id' => 456,
            'referral_code' => 'REF123',
            'admin_id' => $this->user->id
        ]
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

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

    expect($response->json('data.amount'))->toBe(30000)
        ->and($response->json('data.user.id'))->toBe($this->customer->id)
        ->and($response->json('data.type.value'))->toBe('bonus');

    // Verify transaction was created in database
    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $this->customer->id,
        'amount' => 30000,
        'type' => 'bonus',
        'source_type' => 'campaign',
        'source_id' => $this->campaign->id,
    ]);

    // Verify campaign usage count was incremented
    expect($this->campaign->fresh()->total_usage_count)->toBe(1);
});

it('returns existing transaction for same trigger event (idempotency)', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    // Create existing transaction with same trigger event
    $existingTransaction = WalletTransaction::factory()->create([
        'user_id' => $this->customer->id,
        'type' => 'bonus',
        'source_type' => 'campaign',
        'source_id' => $this->campaign->id,
        'amount' => 30000,
        'metadata' => ['trigger_event' => 'first_purchase']
    ]);

    $requestData = [

        'user_id' => $this->customer->id,
        'trigger_event' => 'first_purchase'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertOk();

    expect($response->json('data.id'))->toBe($existingTransaction->id);
    expect($this->campaign->fresh()->total_usage_count)->toBe(0); // Should not increment
});

it('processes manual bonus without trigger event', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $requestData = [

        'user_id' => $this->customer->id,
        // No trigger_event (manual trigger)
        'metadata' => [
            'admin_reason' => 'Customer service bonus',
            'ticket_id' => 'CS-12345'
        ]
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertOk();

    $transaction = WalletTransaction::where('user_id', $this->customer->id)->first();
    expect($transaction->metadata['trigger_event'])->toBeNull();
    expect($transaction->metadata['admin_reason'])->toBe('Customer service bonus');
});

it('validates required fields', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([ 'user_id']);
});

it('validates campaign exists', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $requestData = [
        'user_id' => $this->customer->id,
        'trigger_event' => 'test_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',99), $requestData);

    $response->assertNotFound();
});

it('rejects processing when campaign is inactive', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $this->campaign->update(['is_active' => false]);

    $requestData = [

        'user_id' => $this->customer->id,
        'trigger_event' => 'test_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => [
                'bonus'
            ]
        ]);
});

it('rejects processing when campaign is expired', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $this->campaign->update([
        'starts_at' => Carbon::now()->subMonth(),
        'ends_at' => Carbon::now()->subDay(),
    ]);

    $requestData = [
        'user_id' => $this->customer->id,
        'trigger_event' => 'test_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertUnprocessable();
    expect($response->json('errors.bonus'))->toContain(__('validation.custom.campaign_expired'));
});

it('rejects processing when total usage limit reached', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $this->campaign->update([
        'usage_limit_total' => 10,
        'total_usage_count' => 10,
    ]);

    $requestData = [
        'user_id' => $this->customer->id,
        'trigger_event' => 'test_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertUnprocessable();
});

it('rejects processing when user has reached per-user limit', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    // Create transactions to reach per-user limit (3)
    WalletTransaction::factory()->count(3)->create([
        'user_id' => $this->customer->id,
        'source_type' => 'campaign',
        'source_id' => $this->campaign->id,
        'type' => 'bonus',
    ]);

    $requestData = [
        'user_id' => $this->customer->id,
        'trigger_event' => 'new_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertUnprocessable();
    expect($response->json('errors.bonus'))->toContain(__('validation.custom.already_claimed'));
});

it('rejects processing when user has no wallet', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();

    $requestData = [

        'user_id' => $userWithoutWallet->id,
        'trigger_event' => 'test_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertUnprocessable();
});

it('allows multiple bonuses for different trigger events', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    // First bonus
    $response1 = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), [

        'user_id' => $this->customer->id,
        'trigger_event' => 'first_purchase'
    ]);

    $response1->assertOk();

    // Second bonus with different trigger event
    $response2 = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), [

        'user_id' => $this->customer->id,
        'trigger_event' => 'referral_completed'
    ]);

    $response2->assertOk();

    // Should create two separate transactions
    expect($response1->json('data.id'))->not->toBe($response2->json('data.id'));
    expect($this->campaign->fresh()->total_usage_count)->toBe(2);
});

it('includes trigger event in transaction description', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $requestData = [

        'user_id' => $this->customer->id,
        'trigger_event' => 'milestone_reached'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertOk();

    expect($response->json('data.description'))->toContain('Referral Bonus Campaign');
    expect($response->json('data.description'))->toContain('milestone_reached');
});

it('handles complex metadata correctly', function () {
    $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_PROCESS_BONUS]);

    $complexMetadata = [
        'event_details' => [
            'event_type' => 'referral_signup',
            'referred_user' => [
                'id' => 123,
                'email' => 'referred@example.com',
                'signup_date' => '2023-09-04'
            ],
            'referral_chain_length' => 2
        ],
        'bonus_calculations' => [
            'base_amount' => 30000,
            'multiplier' => 1.0,
            'currency' => 'IRR'
        ]
    ];

    $requestData = [

        'user_id' => $this->customer->id,
        'trigger_event' => 'complex_referral',
        'metadata' => $complexMetadata
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertOk();

    $transaction = WalletTransaction::where('user_id', $this->customer->id)->first();
    expect($transaction->metadata['event_details']['referred_user']['id'])->toBe(123);
    expect($transaction->metadata['bonus_calculations']['base_amount'])->toBe(30000);
});

it('denies access without permission', function () {
    // Staff doesn't have WALLET_CAMPAIGN_PROCESS_BONUS permission
    $this->unauthorized_user();
    $requestData = [
        'user_id' => $this->customer->id,
        'trigger_event' => 'test_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertForbidden();
});

it('denies access to non-staff users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $requestData = [
        'user_id' => $this->customer->id,
        'trigger_event' => 'test_event'
    ];

    $response = $this->postJson(route('api.v1.admin.wallet-campaigns.process-bonus',$this->campaign), $requestData);

    $response->assertUnauthorized();
});
