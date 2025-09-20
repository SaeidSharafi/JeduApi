<?php

declare(strict_types=1);

use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer = User::factory()->create();
    $this->user     = Staff::factory()->create();

    $this->campaign = WalletCampaign::factory()->create([
        'name'                 => 'Test Campaign',
        'type'                 => CampaignTypeEnum::WELCOME_GIFT,
        'amount'               => 50000,
        'is_active'            => true,
        'usage_limit_total'    => 1000,
        'usage_limit_per_user' => 2,
        'total_usage_count'    => 0,
        'starts_at'            => Carbon::now()->subDay(),
        'ends_at'              => Carbon::now()->addMonth(),
        'created_by'           => $this->user->id,
    ]);
});

describe('TriggerCampaignAllocationController', function (): void {
    it('can trigger manual campaign allocation successfully', function (): void {
        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'Manual allocation by admin',
                'metadata'     => ['admin_notes' => 'Special allocation'],
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'type',
                'amount',
                'description',
                'metadata',
                'created_at',
            ],
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id'     => $this->customer->id,
            'type'        => 'gift',
            'amount'      => $this->campaign->amount,
            'source_type' => TransactionSourceEnum::CAMPAIGN->value,
            'source_id'   => $this->campaign->id,
        ]);

        $this->campaign->refresh();
        expect($this->campaign->total_usage_count)->toBe(1);
    });

    it('can trigger event-based campaign allocation successfully', function (): void {
        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type'  => 'event',
                'trigger_event' => 'user_registration',
                'metadata'      => ['event_data' => ['ip' => '127.0.0.1']],
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'type',
                'amount',
                'description',
                'metadata',
                'created_at',
            ],
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id'     => $this->customer->id,
            'type'        => 'gift',
            'amount'      => $this->campaign->amount,
            'source_type' => TransactionSourceEnum::CAMPAIGN->value,
            'source_id'   => $this->campaign->id,
        ]);

        $this->campaign->refresh();
        expect($this->campaign->total_usage_count)->toBe(1);
    });

    it('requires trigger_type field', function (): void {
        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'reason' => 'Manual allocation by admin',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['trigger_type']);
    });

    it('requires trigger_event when trigger_type is event', function (): void {
        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'event',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['trigger_event']);
    });

    it('validates trigger_type values', function (): void {
        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'invalid_type',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['trigger_type']);
    });

    it('prevents duplicate allocations for same user and campaign with manual trigger', function (): void {
        $this->campaign->usage_limit_per_user = 1;
        $this->campaign->save();
        // First allocation
        $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'First allocation',
            ])
            ->assertSuccessful();

        // Second allocation attempt
        $response = $this
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'Second allocation',
            ]);

        $response->assertStatus(422);
    });

    it('allows multiple allocations for different trigger events', function (): void {
        // First event-based allocation
        $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type'  => 'event',
                'trigger_event' => 'user_registration',
            ])
            ->assertSuccessful();

        // Second event-based allocation with different trigger
        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type'  => 'event',
                'trigger_event' => 'first_purchase',
            ]);

        $response->assertSuccessful();
    });

    it('prevents allocation when campaign usage limit is exceeded', function (): void {
        // Set campaign to have only 1 total usage
        $this->campaign->update(['usage_limit_total' => 1]);

        // Create another user and allocate to them first
        $otherUser = User::factory()->create();
        $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$otherUser, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'First allocation',
            ])
            ->assertSuccessful();

        // Try to allocate to our test customer
        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'Second allocation',
            ]);

        $response->assertStatus(422);
    });

    it('prevents allocation when campaign is inactive', function (): void {
        $this->campaign->update(['is_active' => false]);

        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'Test allocation',
            ]);

        $response->assertStatus(422);
    });

    it('prevents allocation when campaign has ended', function (): void {
        $this->campaign->update(['ends_at' => Carbon::now()->subDay()]);

        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'Test allocation',
            ]);

        $response->assertStatus(422);
    });

    it('prevents allocation when campaign has not started', function (): void {
        $this->campaign->update(['starts_at' => Carbon::now()->addDay()]);

        $response = $this->authorized_user([App\Enums\PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'Test allocation',
            ]);

        $response->assertStatus(422);
    });

    it('requires permission to allocate campaigns', function (): void {
        $response = $this->authorized_user([])
            ->postJson(route('api.v1.admin.users.wallet-campaigns.trigger-allocation', [$this->customer, $this->campaign]), [
                'trigger_type' => 'manual',
                'reason'       => 'Test allocation',
            ]);

        $response->assertForbidden();
    });
});
