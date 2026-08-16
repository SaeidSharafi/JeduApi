<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Enums\WalletCampaign\ThresholdScopeEnum;
use App\Models\Staff;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->user = Staff::factory()->create();
});

describe('index', function (): void {
    beforeEach(function (): void {
        $this->campaigns = WalletCampaign::factory()->count(5)->create([
            'type'       => CampaignTypeEnum::WELCOME_GIFT,
            'created_by' => $this->user->id,
        ]);
    });

    it('returns paginated list of campaigns for authorized staff', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_VIEW_ANY]);

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
                            'threshold_scope',
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
                        ],
                    ],
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        expect($response->json('data.data'))->toHaveCount(5);
    });

    it('supports filtering by campaign type', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_VIEW_ANY]);

        WalletCampaign::factory()->create([
            'type'       => CampaignTypeEnum::BIRTHDAY_GIFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/admin/wallet-campaigns?filter[type]=birthday_gift');

        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1);
        expect($response->json('data.data.0.type'))->toBe('birthday_gift');
    });

    it('supports sorting by creation date', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_VIEW_ANY]);

        $response = $this->getJson('/api/v1/admin/wallet-campaigns?sort=created_at');

        $response->assertOk();

        $data        = $response->json('data.data');
        $dates       = collect($data)->pluck('created_at')->toArray();
        $sortedDates = collect($dates)->sort()->values()->toArray();

        expect($dates)->toBe($sortedDates);
    });

    it('denies access without permission', function (): void {
        $this->unauthorized_user();
        $response = $this->getJson('/api/v1/admin/wallet-campaigns');

        $response->assertForbidden();
    });
});

describe('store', function (): void {
    it('creates campaign with valid data', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $createData = [
            'name'                 => 'New Registration Bonus',
            'description'          => 'Welcome bonus for new users',
            'type'                 => CampaignTypeEnum::REGISTRATION_BONUS->value,
            'threshold_scope'      => ThresholdScopeEnum::WINDOWED->value,
            'is_active'            => true,
            'amount'               => 50000,
            'usage_limit_total'    => 1000,
            'usage_limit_per_user' => 1,
            'starts_at'            => $this->toJalalitString(now(), 'Y-m-d'),
            'ends_at'              => $this->toJalalitString(now()->addMonth(), 'Y-m-d'),
            'metadata'             => ['source' => 'admin_panel'],
        ];

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', $createData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'type',
                    'threshold_scope',
                    'amount',
                ],
            ]);

        expect($response->json('data.name'))->toBe('New Registration Bonus');
        expect($response->json('data.threshold_scope'))->toBe(ThresholdScopeEnum::WINDOWED->value);

        $this->assertDatabaseHas('wallet_campaigns', [
            'name'            => 'New Registration Bonus',
            'type'            => CampaignTypeEnum::REGISTRATION_BONUS->value,
            'threshold_scope' => ThresholdScopeEnum::WINDOWED->value,
        ]);
    });

    it('creates a lifetime campaign without dates', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $createData = [
            'name'            => 'Lifetime Loyalty Bonus',
            'description'     => 'Bonus measured across all history',
            'type'            => CampaignTypeEnum::LOYALTY_REWARD->value,
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'is_active'       => true,
            'amount'          => 25000,
            'metadata'        => null,
        ];

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', $createData);

        $response->assertCreated();
        expect($response->json('data.threshold_scope'))->toBe(ThresholdScopeEnum::LIFETIME->value);
        expect($response->json('data.starts_at'))->toBeNull();
        expect($response->json('data.ends_at'))->toBeNull();

        $this->assertDatabaseHas('wallet_campaigns', [
            'name'            => 'Lifetime Loyalty Bonus',
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);
    });

    it('rejects a windowed campaign without both dates', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $createData = [
            'name'            => 'Windowed Without Dates',
            'type'            => CampaignTypeEnum::SEASONAL_BONUS->value,
            'threshold_scope' => ThresholdScopeEnum::WINDOWED->value,
            'is_active'       => true,
            'amount'          => 30000,
            'starts_at'       => null,
            'ends_at'         => null,
        ];

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', $createData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'ends_at']);
    });

    it('rejects a lifetime campaign with dates', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $createData = [
            'name'            => 'Lifetime With Dates',
            'type'            => CampaignTypeEnum::LOYALTY_REWARD->value,
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'is_active'       => true,
            'amount'          => 30000,
            'starts_at'       => $this->toJalalitString(now(), 'Y-m-d'),
            'ends_at'         => $this->toJalalitString(now()->addMonth(), 'Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', $createData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'ends_at']);
    });

    it('rejects an invalid threshold_scope value', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $createData = [
            'name'            => 'Bad Scope',
            'type'            => CampaignTypeEnum::WELCOME_GIFT->value,
            'threshold_scope' => 'quarterly',
            'is_active'       => true,
            'amount'          => 30000,
        ];

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', $createData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['threshold_scope']);
    });

    it('rejects a windowed campaign whose end date is not after start date', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $createData = [
            'name'            => 'Inverted Window',
            'type'            => CampaignTypeEnum::SEASONAL_BONUS->value,
            'threshold_scope' => ThresholdScopeEnum::WINDOWED->value,
            'is_active'       => true,
            'amount'          => 30000,
            'starts_at'       => $this->toJalalitString(now()->addMonth(), 'Y-m-d'),
            'ends_at'         => $this->toJalalitString(now(), 'Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', $createData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);
    });

    it('validates required fields', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_CREATE]);

        $response = $this->postJson('/api/v1/admin/wallet-campaigns', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'threshold_scope', 'amount']);
    });

    it('denies access without permission', function (): void {
        $this->unauthorized_user();
        $response = $this->postJson('/api/v1/admin/wallet-campaigns', [
            'name'            => 'Test Campaign',
            'type'            => CampaignTypeEnum::WELCOME_GIFT->value,
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'is_active'       => true,
            'amount'          => 10000,
        ]);

        $response->assertForbidden();
    });
});

describe('show', function (): void {
    beforeEach(function (): void {
        $this->campaign = WalletCampaign::factory()->create([
            'created_by' => $this->user->id,
        ]);
    });

    it('returns campaign details for authorized staff', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_VIEW]);

        $response = $this->getJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'threshold_scope',
                    'is_active',
                    'amount',
                    'usage_limit_total',
                    'usage_limit_per_user',
                    'total_usage_count',
                    'transactions_count',
                    'created_by',
                    'created_at',
                    'updated_at',
                ],
            ]);

        expect($response->json('data.id'))->toBe($this->campaign->id);
    });

    it('includes transaction count', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_VIEW]);

        // Create some transactions for this campaign
        WalletTransaction::factory()->count(3)->create([
            'source_type' => 'campaign',
            'source_id'   => $this->campaign->id,
        ]);

        $response = $this->getJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertOk();
        expect($response->json('data.transactions_count'))->toBe(3);
    });

    it('denies access without permission', function (): void {
        $this->unauthorized_user();
        $response = $this->getJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertForbidden();
    });

    it('returns 404 for non-existent campaign', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_VIEW]);

        $response = $this->getJson('/api/v1/admin/wallet-campaigns/99999');

        $response->assertNotFound();
    });
});

describe('update', function (): void {
    beforeEach(function (): void {
        $this->campaign = WalletCampaign::factory()->create([
            'name'       => 'Original Campaign',
            'created_by' => $this->user->id,
        ]);
    });

    it('updates campaign with valid data', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_UPDATE]);

        $updateData = [
            'name'                 => 'Updated Campaign Name',
            'description'          => 'Updated description',
            'type'                 => $this->campaign->type->value,
            'threshold_scope'      => ThresholdScopeEnum::WINDOWED->value,
            'is_active'            => false,
            'amount'               => 75000,
            'usage_limit_total'    => 500,
            'usage_limit_per_user' => 2,
            'starts_at'            => $this->toJalalitString(now(), 'Y-m-d'),
            'ends_at'              => $this->toJalalitString(now()->addMonth(), 'Y-m-d'),
            'metadata'             => ['updated' => true],
        ];

        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", $updateData);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'threshold_scope',
                    'amount',
                    'is_active',
                ],
            ]);

        expect($response->json('data.name'))->toBe('Updated Campaign Name');
        expect($response->json('data.amount'))->toBe(75000);
        expect($response->json('data.threshold_scope'))->toBe(ThresholdScopeEnum::WINDOWED->value);
        expect($response->json('data.is_active'))->toBeFalse();

        $this->assertDatabaseHas('wallet_campaigns', [
            'id'              => $this->campaign->id,
            'name'            => 'Updated Campaign Name',
            'amount'          => 75000,
            'threshold_scope' => ThresholdScopeEnum::WINDOWED->value,
            'is_active'       => false,
        ]);
    });

    it('updates a campaign to lifetime scope', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_UPDATE]);

        $updateData = [
            'name'            => 'Converted To Lifetime',
            'description'     => $this->campaign->description,
            'type'            => $this->campaign->type->value,
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'is_active'       => $this->campaign->is_active,
            'amount'          => $this->campaign->amount,
            'starts_at'       => null,
            'ends_at'         => null,
            'metadata'        => $this->campaign->metadata,
        ];

        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", $updateData);

        $response->assertOk();
        expect($response->json('data.threshold_scope'))->toBe(ThresholdScopeEnum::LIFETIME->value);
        expect($response->json('data.starts_at'))->toBeNull();
        expect($response->json('data.ends_at'))->toBeNull();

        $this->assertDatabaseHas('wallet_campaigns', [
            'id'              => $this->campaign->id,
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'starts_at'       => null,
            'ends_at'         => null,
        ]);
    });

    it('rejects updating a windowed campaign without both dates', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_UPDATE]);

        $updateData = [
            'name'            => $this->campaign->name,
            'description'     => $this->campaign->description,
            'type'            => $this->campaign->type->value,
            'threshold_scope' => ThresholdScopeEnum::WINDOWED->value,
            'is_active'       => $this->campaign->is_active,
            'amount'          => $this->campaign->amount,
            'starts_at'       => null,
            'ends_at'         => null,
            'metadata'        => $this->campaign->metadata,
        ];

        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", $updateData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'ends_at']);
    });

    it('rejects updating a lifetime campaign with dates', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_UPDATE]);

        $updateData = [
            'name'            => $this->campaign->name,
            'description'     => $this->campaign->description,
            'type'            => $this->campaign->type->value,
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'is_active'       => $this->campaign->is_active,
            'amount'          => $this->campaign->amount,
            'starts_at'       => $this->toJalalitString(now(), 'Y-m-d'),
            'ends_at'         => $this->toJalalitString(now()->addMonth(), 'Y-m-d'),
            'metadata'        => $this->campaign->metadata,
        ];

        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", $updateData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'ends_at']);
    });

    it('validates update data', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_UPDATE]);

        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", [
            'name'            => '', // Invalid empty name
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'amount'          => -1000, // Invalid negative amount
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'amount']);
    });

    it('denies access without permission', function (): void {
        $this->unauthorized_user();
        $response = $this->putJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}", [
            'name'            => 'Updated Name',
            'type'            => $this->campaign->type->value,
            'threshold_scope' => ThresholdScopeEnum::LIFETIME->value,
            'amount'          => 60000,
            'is_active'       => true,
        ]);

        $response->assertForbidden();
    });
});

describe('destroy', function (): void {
    beforeEach(function (): void {
        $this->campaign = WalletCampaign::factory()->create([
            'created_by' => $this->user->id,
        ]);
    });

    it('deletes campaign without transactions', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_DELETE]);

        $response = $this->deleteJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('wallet_campaigns', [
            'id' => $this->campaign->id,
        ]);
    });

    it('prevents deletion of campaign with transactions', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_DELETE]);

        // Create transactions for this campaign
        WalletTransaction::factory()->create([
            'source_type' => TransactionSourceEnum::CAMPAIGN,
            'source_id'   => $this->campaign->id,
        ]);

        $response = $this->deleteJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertUnprocessable()
            ->assertJson([
                'message' => __('messages.campaign_has_transactions_cannot_delete'),
            ]);

        $this->assertDatabaseHas('wallet_campaigns', [
            'id' => $this->campaign->id,
        ]);
    });

    it('denies access without permission', function (): void {
        $this->unauthorized_user();
        $response = $this->deleteJson("/api/v1/admin/wallet-campaigns/{$this->campaign->id}");

        $response->assertForbidden();
    });

    it('returns 404 for non-existent campaign', function (): void {
        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_DELETE]);

        $response = $this->deleteJson('/api/v1/admin/wallet-campaigns/99999');

        $response->assertNotFound();
    });
});
