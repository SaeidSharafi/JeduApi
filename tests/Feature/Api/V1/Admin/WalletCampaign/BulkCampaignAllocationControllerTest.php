<?php

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\AuthTestTrait;
use function Pest\Laravel\postJson;

uses(AuthTestTrait::class);

beforeEach(function () {
    $this->users = User::factory()->count(3)->create();
    $this->staff = Staff::factory()->create();

    $this->campaign = WalletCampaign::factory()->create([
        'name' => 'Bulk Test Campaign',
        'type' => CampaignTypeEnum::WELCOME_GIFT,
        'amount' => 25000,
        'is_active' => true,
        'usage_limit_total' => 1000,
        'usage_limit_per_user' => 1,
        'total_usage_count' => 0,
        'starts_at' => Carbon::now()->subDay(),
        'ends_at' => Carbon::now()->addMonth(),
        'created_by' => $this->staff->id,
    ]);
});

describe('BulkCampaignAllocationController', function () {
    it('can trigger bulk manual campaign allocation successfully', function () {
        $userIds = $this->users->pluck('id')->toArray();
        $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);
        $response = postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'manual',
                'reason' => 'Bulk manual allocation',
                'metadata' => ['admin_notes' => 'Holiday bonus for all users']
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'success_count',
                'failure_count',
                'results' => [
                    '*' => [
                        'user_id',
                        'status',
                        'transaction_id',
                        'amount'
                    ]
                ]
            ]
        ]);

        $responseData = $response->json('data');
        expect($responseData['success_count'])->toBe(3);
        expect($responseData['failure_count'])->toBe(0);
        expect(count($responseData['results']))->toBe(3);

        // Verify all transactions were created
        foreach ($userIds as $userId) {
            $this->assertDatabaseHas('wallet_transactions', [
                'user_id' => $userId,
                'type' => 'gift',
                'amount' => $this->campaign->amount,
                'source_type' => TransactionSourceEnum::CAMPAIGN->value,
                'source_id' => $this->campaign->id,
            ]);
        }

        // Verify campaign usage count increased
        $this->campaign->refresh();
        expect($this->campaign->total_usage_count)->toBe(3);
    });

    it('can trigger bulk event-based campaign allocation successfully', function () {
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'event',
                'trigger_event' => 'bulk_registration_bonus',
                'metadata' => ['batch_id' => 'BULK_001']
            ]);

        $response->assertSuccessful();

        $responseData = $response->json('data');
        expect($responseData['success_count'])->toBe(3);
        expect($responseData['failure_count'])->toBe(0);

        // Verify all transactions were created with correct type
        foreach ($userIds as $userId) {
            $this->assertDatabaseHas('wallet_transactions', [
                'user_id' => $userId,
                'type' => 'gift',
                'amount' => $this->campaign->amount,
                'source_type' => TransactionSourceEnum::CAMPAIGN->value,
                'source_id' => $this->campaign->id,
            ]);
        }
    });

    it('handles partial failures gracefully', function () {
        // Create one user with a wallet that already has this allocation
        $existingUser = $this->users->first();
        $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$existingUser, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason' => 'Pre-existing allocation',
            ])
            ->assertSuccessful();

        // Now try bulk allocation including the user who already received it
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'manual',
                'reason' => 'Bulk allocation with conflict',
            ]);

        $response->assertStatus(207); // Multi-Status for partial success

        $responseData = $response->json('data');
        expect($responseData['success_count'])->toBe(2); // Only 2 new allocations
        expect($responseData['failure_count'])->toBe(1); // 1 failed (duplicate)

        // Check results array
        $results = $responseData['results'];
        $successfulResults = collect($results)->where('status', 'success');
        $failedResults = collect($results)->where('status', 'failed');

        expect($successfulResults->count())->toBe(2);
        expect($failedResults->count())->toBe(1);
        expect($failedResults->first()['user_id'])->toBe($existingUser->id);
    });
    //it('handles missing users gracefully', function () {
    //    $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE]);
    //    $userIds = $this->users->pluck('id')->toArray();
    //    $userIds[] = 999999; // Non-existent user ID
    //    $response = $this
    //        ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
    //            'user_ids' => $userIds,
    //            'trigger_type' => 'manual',
    //            'reason' => 'Bulk allocation with conflict',
    //        ]);
    //
    //    $response->assertStatus(207); // Multi-Status for partial success
    //
    //    $responseData = $response->json('data');
    //    expect($responseData['success_count'])->toBe(2); // Only 2 new allocations
    //    expect($responseData['failure_count'])->toBe(1); // 1 failed (duplicate)
    //
    //    // Check results array
    //    $results = $responseData['results'];
    //    $successfulResults = collect($results)->where('status', 'success');
    //    $failedResults = collect($results)->where('status', 'failed');
    //
    //    expect($successfulResults->count())->toBe(2);
    //    expect($failedResults->count())->toBe(1);
    //    expect($failedResults->first()['user_id'])->toBe($existingUser->id);
    //});
    it('requires user_ids field', function () {
        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'trigger_type' => 'manual',
                'reason' => 'Bulk allocation',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_ids']);
    });

    it('requires at least one user_id', function () {
        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => [],
                'trigger_type' => 'manual',
                'reason' => 'Bulk allocation',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_ids']);
    });

    it('limits bulk operations to maximum users', function () {
        // Create more than 100 users (the limit we set)
        $manyUserIds = range(1, 101);

        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $manyUserIds,
                'trigger_type' => 'manual',
                'reason' => 'Too many users',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_ids']);
    });

    it('validates that all user_ids exist', function () {
        $userIds = [999999, 999998]; // Non-existent users

        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'manual',
                'reason' => 'Invalid users',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['user_ids.0', 'user_ids.1']);
    });

    it('requires trigger_type field', function () {
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'reason' => 'Bulk allocation',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['trigger_type']);
    });

    it('requires trigger_event when trigger_type is event', function () {
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'event',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['trigger_event']);
    });

    it('validates trigger_type values', function () {
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'invalid_type',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['trigger_type']);
    });

    it('requires permission to allocate campaigns', function () {
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this->authorized_user([])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'manual',
                'reason' => 'Test allocation',
            ]);

        $response->assertForbidden();
    });

    it('handles campaign that is inactive', function () {
        $this->campaign->update(['is_active' => false]);
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this->authorized_user([\App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.bulk-trigger-allocation', $this->campaign), [
                'user_ids' => $userIds,
                'trigger_type' => 'manual',
                'reason' => 'Test allocation',
            ]);

        $response->assertStatus(207); // Multi-Status (all will fail)

        $responseData = $response->json('data');
        expect($responseData['success_count'])->toBe(0);
        expect($responseData['failure_count'])->toBe(3);
    });
});
