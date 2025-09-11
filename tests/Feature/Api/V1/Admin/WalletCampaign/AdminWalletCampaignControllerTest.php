<?php

use App\Enums\PermissionEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function () {
    $this->user = Staff::factory()->create();
});

describe('index', function () {
    beforeEach(function () {
        $this->campaigns = WalletCampaign::factory()->count(5)->create([
            'type' => CampaignTypeEnum::WELCOME_GIFT,
            'created_by' => $this->user->id,
        ]);
    });

    it('returns paginated list of campaigns for authorized staff', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_VIEW_ANY]);

        $response = $this->getJson('/api/v1/admin/wallet-campaigns');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => [
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
                            'updated_at'
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);

        expect($response->json('data.data'))->toHaveCount(5);
    });

    it('supports filtering by campaign type', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_VIEW_ANY]);

        WalletCampaign::factory()->create([
            'type' => CampaignTypeEnum::BIRTHDAY_GIFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/admin/wallet-campaigns?filter[type]=birthday_gift');

        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1);
        expect($response->json('data.data.0.type'))->toBe('birthday_gift');
    });

    it('supports sorting by creation date', function () {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_VIEW_ANY]);

        $response = $this->getJson('/api/v1/admin/wallet-campaigns?sort=created_at');

        $response->assertOk();

        $data = $response->json('data.data');
        $dates = collect($data)->pluck('created_at')->toArray();
        $sortedDates = collect($dates)->sort()->values()->toArray();

        expect($dates)->toBe($sortedDates);
    });

    it('denies access without permission', function () {
        $this->unauthorized_user();
        $response = $this->getJson('/api/v1/admin/wallet-campaigns');

        $response->assertForbidden();
    });
});

describe('store', function () {
    it('creates campaign with valid data', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $createData = [
            'name' => 'New Registration Bonus',
            'description' => 'Welcome bonus for new users',
            'type' => CampaignTypeEnum::REGISTRATION_BONUS->value,
            'is_active' => true,
            'amount' => 50000,
            'usage_limit_total' => 1000,
            'usage_limit_per_user' => 1,
            'starts_at' => $this->toJalalitString(now(),'Y-m-d'),
            'ends_at' =>  $this->toJalalitString(now()->addMonth(),'Y-m-d'),
            'metadata' => ['source' => 'admin_panel']
        ];

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', $createData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'type',
                    'amount',
                ]
            ]);

        expect($response->json('data.name'))->toBe('New Registration Bonus');

        $this->assertDatabaseHas('wallet_campaigns', [
            'name' => 'New Registration Bonus',
            'type' => CampaignTypeEnum::REGISTRATION_BONUS->value,
        ]);
    });

    it('validates required fields', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'amount']);
    });

    it('denies access without permission', function () {
        $this->unauthorized_user();
        $response = $this->postJson('/api/v1/admin/wallet-campaigns', [
            'name' => 'Test Campaign',
            'type' => CampaignTypeEnum::WELCOME_GIFT->value,
            'is_active' => true,
            'amount' => 10000,
        ]);

        $response->assertForbidden();
    });
});

describe('show', function () {
    beforeEach(function () {
        $this->campaign = WalletCampaign::factory()->create([
            'created_by' => $this->user->id,
        ]);
    });

    it('returns campaign details for authorized staff', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_VIEW]);

        $response = $this->getJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'is_active',
                    'amount',
                    'usage_limit_total',
                    'usage_limit_per_user',
                    'total_usage_count',
                    'transactions_count',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ]);

        expect($response->json('data.id'))->toBe($this->campaign->id);
    });

    it('includes transaction count', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_VIEW]);

        // Create some transactions for this campaign
        WalletTransaction::factory()->count(3)->create([
            'source_type' => 'campaign',
            'source_id' => $this->campaign->id,
        ]);

        $response = $this->getJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertOk();
        expect($response->json('data.transactions_count'))->toBe(3);
    });

    it('denies access without permission', function () {
        $this->unauthorized_user();
        $response = $this->getJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertForbidden();
    });

    it('returns 404 for non-existent campaign', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_VIEW]);

        $response = $this->getJson('/api/v1/admin/wallet-campaigns/99999');

        $response->assertNotFound();
    });
});

describe('update', function () {
    beforeEach(function () {
        $this->campaign = WalletCampaign::factory()->create([
            'name' => 'Original Campaign',
            'created_by' => $this->user->id,
        ]);
    });

    it('updates campaign with valid data', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_UPDATE]);

        $updateData = [
            'name' => 'Updated Campaign Name',
            'description' => 'Updated description',
            'type' => $this->campaign->type->value,
            'is_active' => false,
            'amount' => 75000,
            'usage_limit_total' => 500,
            'usage_limit_per_user' => 2,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => ['updated' => true]
        ];

        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", $updateData);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'amount',
                    'is_active'
                ]
            ]);

        expect($response->json('data.name'))->toBe('Updated Campaign Name');
        expect($response->json('data.amount'))->toBe(75000);
        expect($response->json('data.is_active'))->toBeFalse();

        $this->assertDatabaseHas('wallet_campaigns', [
            'id' => $this->campaign->id,
            'name' => 'Updated Campaign Name',
            'amount' => 75000,
            'is_active' => false,
        ]);
    });

    it('validates update data', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_UPDATE]);

        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", [
            'name' => '', // Invalid empty name
            'amount' => -1000, // Invalid negative amount
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'amount']);
    });

    it('denies access without permission', function () {
        $this->unauthorized_user();
        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", [
            'name' => 'Updated Name',
            'type' => $this->campaign->type->value,
            'amount' => 60000,
            'is_active' => true,
        ]);

        $response->assertForbidden();
    });
});

describe('destroy', function () {
    beforeEach(function () {
        $this->campaign = WalletCampaign::factory()->create([
            'created_by' => $this->user->id,
        ]);
    });

    it('deletes campaign without transactions', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_DELETE]);

        $response = $this->deleteJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('wallet_campaigns', [
            'id' => $this->campaign->id,
        ]);
    });

    it('prevents deletion of campaign with transactions', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_DELETE]);

        // Create transactions for this campaign
        WalletTransaction::factory()->create([
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id' => $this->campaign->id,
        ]);

        $response = $this->deleteJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertUnprocessable()
            ->assertJson([
                'message' => __('messages.campaign_has_transactions_cannot_delete')
            ]);

        $this->assertDatabaseHas('wallet_campaigns', [
            'id' => $this->campaign->id,
        ]);
    });

    it('denies access without permission', function () {
        $this->unauthorized_user();
        $response = $this->deleteJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertForbidden();
    });

    it('returns 404 for non-existent campaign', function () {
        $this->authorized_user([ PermissionEnum::WALLET_CAMPAIGN_DELETE]);

        $response = $this->deleteJson('/api/v1/admin/wallet-campaigns/99999');

        $response->assertNotFound();
    });
});
