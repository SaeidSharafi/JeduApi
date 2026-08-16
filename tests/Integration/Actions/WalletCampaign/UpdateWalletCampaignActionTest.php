<?php

declare(strict_types=1);

use App\Actions\Admin\WalletCampaign\UpdateWalletCampaignAction;
use App\Data\Admin\WalletCampaign\WalletCampaignCreateData;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Enums\WalletCampaign\ThresholdScopeEnum;
use App\Models\Staff;
use App\Models\WalletCampaign;
use Carbon\Carbon;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->user     = Staff::factory()->create();
    $this->campaign = WalletCampaign::factory()->create([
        'name'                 => 'Original Campaign',
        'description'          => 'Original description',
        'type'                 => CampaignTypeEnum::REGISTRATION_BONUS,
        'is_active'            => true,
        'amount'               => 10000,
        'usage_limit_total'    => 100,
        'usage_limit_per_user' => 1,
        'starts_at'            => Carbon::now(),
        'ends_at'              => Carbon::now()->addWeek(),
        'metadata'             => ['original' => true],
        'created_by'           => $this->user->id,
    ]);

    $this->action = new UpdateWalletCampaignAction();
});

it('successfully updates all campaign fields', function (): void {
    $newStartDate = Carbon::now()->addDay();
    $newEndDate   = Carbon::now()->addMonth();

    $updateData = new WalletCampaignCreateData(
        name: 'Updated Campaign Name',
        description: 'Updated campaign description',
        type: CampaignTypeEnum::WELCOME_GIFT->value,
        threshold_scope: ThresholdScopeEnum::WINDOWED->value,
        is_active: false,
        amount: 25000,
        usage_limit_total: 500,
        usage_limit_per_user: 3,
        starts_at: $newStartDate,
        ends_at: $newEndDate,
        metadata: ['updated' => true, 'version' => 2]
    );

    $updatedCampaign = $this->action->execute($this->campaign, $updateData);

    expect($updatedCampaign->id)->toBe($this->campaign->id);
    expect($updatedCampaign->name)->toBe('Updated Campaign Name');
    expect($updatedCampaign->description)->toBe('Updated campaign description');
    expect($updatedCampaign->type)->toBe(CampaignTypeEnum::WELCOME_GIFT);
    expect($updatedCampaign->is_active)->toBeFalse();
    expect($updatedCampaign->amount)->toBe(25000);
    expect($updatedCampaign->usage_limit_total)->toBe(500);
    expect($updatedCampaign->usage_limit_per_user)->toBe(3);
    expect($updatedCampaign->starts_at->format('Y-m-d'))->toBe($newStartDate->format('Y-m-d'));
    expect($updatedCampaign->ends_at->format('Y-m-d'))->toBe($newEndDate->format('Y-m-d'));
    expect($updatedCampaign->metadata)->toBe(['updated' => true, 'version' => 2]);

    // Verify persistence in database
    $this->assertDatabaseHas('wallet_campaigns', [
        'id'        => $this->campaign->id,
        'name'      => 'Updated Campaign Name',
        'type'      => CampaignTypeEnum::WELCOME_GIFT->value,
        'amount'    => 25000,
        'is_active' => false,
    ]);
});

it('updates campaign with null dates', function (): void {
    $updateData = new WalletCampaignCreateData(
        name: 'No Date Limits Campaign',
        description: 'Campaign without date restrictions',
        type: CampaignTypeEnum::MANUAL_ALLOCATION->value,
        threshold_scope: ThresholdScopeEnum::LIFETIME->value,
        is_active: true,
        amount: 15000,
        usage_limit_total: null,
        usage_limit_per_user: null,
        starts_at: null,
        ends_at: null,
        metadata: null
    );

    $updatedCampaign = $this->action->execute($this->campaign, $updateData);

    expect($updatedCampaign->starts_at)->toBeNull();
    expect($updatedCampaign->ends_at)->toBeNull();
    expect($updatedCampaign->usage_limit_total)->toBeNull();
    expect($updatedCampaign->usage_limit_per_user)->toBeNull();
    expect($updatedCampaign->metadata)->toBeNull();
});

it('updates only specific fields while preserving others', function (): void {
    $originalUsageCount = $this->campaign->total_usage_count;

    $updateData = WalletCampaignCreateData::from(
        [
            'name'                 => 'Partially Updated Campaign',
            'description'          => $this->campaign->description, // Keep original
            'type'                 => $this->campaign->type->value, // Keep original
            'threshold_scope'      => $this->campaign->threshold_scope->value, // Keep original
            'is_active'            => false, // Change this
            'amount'               => 35000, // Change this
            'usage_limit_total'    => $this->campaign->usage_limit_total, // Keep original
            'usage_limit_per_user' => $this->campaign->usage_limit_per_user, // Keep original
            'starts_at'            => $this->campaign->starts_at->clone(), // Keep original
            'ends_at'              => $this->campaign->ends_at->clone(), // Keep original
            'metadata'             => $this->campaign->metadata, // Keep original
        ]
    );

    $updatedCampaign = $this->action->execute($this->campaign, $updateData);

    // Changed fields
    expect($updatedCampaign->name)->toBe('Partially Updated Campaign');
    expect($updatedCampaign->is_active)->toBeFalse();
    expect($updatedCampaign->amount)->toBe(35000);

    // Preserved fields
    expect($updatedCampaign->description)->toBe($this->campaign->description);
    expect($updatedCampaign->type)->toBe($this->campaign->type);
    expect($updatedCampaign->total_usage_count)->toBe($originalUsageCount);
});

it('handles different campaign types correctly', function (): void {
    foreach (CampaignTypeEnum::cases() as $campaignType) {
        $updateData = new WalletCampaignCreateData(
            name: "Campaign Type: {$campaignType->value}",
            description: "Testing {$campaignType->value} campaign type",
            type: $campaignType->value,
            threshold_scope: ThresholdScopeEnum::LIFETIME->value,
            is_active: true,
            amount: 10000,
            usage_limit_total: 100,
            usage_limit_per_user: 1,
            starts_at: null,
            ends_at: null,
            metadata: ['campaign_type_test' => $campaignType->value]
        );

        $updatedCampaign = $this->action->execute($this->campaign, $updateData);

        expect($updatedCampaign->type)->toBe($campaignType);
        expect($updatedCampaign->metadata['campaign_type_test'])->toBe($campaignType->value);
    }
});

it('handles complex metadata updates', function (): void {
    $complexMetadata = [
        'rules' => [
            'min_order_amount'    => 50000,
            'eligible_categories' => ['electronics', 'books'],
            'excluded_users'      => [1, 2, 3],
        ],
        'notifications' => [
            'email' => true,
            'sms'   => false,
            'push'  => true,
        ],
        'analytics' => [
            'track_source'     => true,
            'conversion_goals' => ['registration', 'purchase'],
        ],
    ];

    $updateData = WalletCampaignCreateData::from(
        [
            'name'                 => $this->campaign->name,
            'description'          => $this->campaign->description,
            'type'                 => $this->campaign->type->value,
            'threshold_scope'      => $this->campaign->threshold_scope->value,
            'is_active'            => $this->campaign->is_active,
            'amount'               => $this->campaign->amount,
            'usage_limit_total'    => $this->campaign->usage_limit_total,
            'usage_limit_per_user' => $this->campaign->usage_limit_per_user,
            'starts_at'            => $this->campaign->starts_at->clone(),
            'ends_at'              => $this->campaign->ends_at->clone(),
            'metadata'             => $complexMetadata,
        ]
    );

    $updatedCampaign = $this->action->execute($this->campaign, $updateData);

    expect($updatedCampaign->metadata)->toBe($complexMetadata);
    expect($updatedCampaign->metadata['rules']['min_order_amount'])->toBe(50000);
    expect($updatedCampaign->metadata['notifications']['email'])->toBeTrue();
});

it('updates timestamps correctly', function (): void {
    $futureStart = Carbon::now()->addDays(5);
    $futureEnd   = Carbon::now()->addDays(30);

    $updateData = new WalletCampaignCreateData(
        name: 'Future Campaign',
        description: 'Campaign with future dates',
        type: CampaignTypeEnum::SEASONAL_BONUS->value,
        threshold_scope: ThresholdScopeEnum::WINDOWED->value,
        is_active: true,
        amount: 40000,
        usage_limit_total: 1000,
        usage_limit_per_user: 2,
        starts_at: $futureStart,
        ends_at: $futureEnd,
        metadata: null
    );

    $updatedCampaign = $this->action->execute($this->campaign, $updateData);

    expect($updatedCampaign->starts_at->format('Y-m-d'))->toBe($futureStart->format('Y-m-d'));
    expect($updatedCampaign->ends_at->format('Y-m-d'))->toBe($futureEnd->format('Y-m-d'));
});

it('returns the same campaign instance', function (): void {
    $updateData = WalletCampaignCreateData::from([
        'name'                 => 'Same Instance Test',
        'description'          => 'Testing instance consistency',
        'type'                 => $this->campaign->type->value,
        'threshold_scope'      => $this->campaign->threshold_scope->value,
        'is_active'            => $this->campaign->is_active,
        'amount'               => $this->campaign->amount,
        'usage_limit_total'    => $this->campaign->usage_limit_total,
        'usage_limit_per_user' => $this->campaign->usage_limit_per_user,
        'starts_at'            => $this->campaign->starts_at,
        'ends_at'              => $this->campaign->ends_at,
        'metadata'             => $this->campaign->metadata,
    ]
    );

    $updatedCampaign = $this->action->execute($this->campaign, $updateData);

    // Should return the same model instance (refreshed from DB)
    expect($updatedCampaign)->toBeInstanceOf(WalletCampaign::class);
    expect($updatedCampaign->id)->toBe($this->campaign->id);
});
