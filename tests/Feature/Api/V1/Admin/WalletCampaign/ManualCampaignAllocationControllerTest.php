<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\WalletCampaign\CampaignTypeEnum;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->users = User::factory()->count(3)->create();
    $this->staff = Staff::factory()->create();

    $this->campaign = WalletCampaign::factory()->create([
        'name'                 => 'Manual Allocation Campaign',
        'type'                 => CampaignTypeEnum::MANUAL_ALLOCATION,
        'amount'               => 25000,
        'is_active'            => true,
        'usage_limit_total'    => 1000,
        'usage_limit_per_user' => 1,
        'total_usage_count'    => 0,
        'starts_at'            => Carbon::now()->subDay(),
        'ends_at'              => Carbon::now()->addMonth(),
        'created_by'           => $this->staff->id,
    ]);
});

describe('ManualCampaignAllocationController', function (): void {
    it('allocates a manual campaign to every listed user', function (): void {
        $userIds = $this->users->pluck('id')->toArray();

        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => $userIds,
                'reason'   => 'Holiday bonus',
                'metadata' => ['admin_notes' => 'Support ticket #123'],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'success_count',
                    'failure_count',
                    'results' => [
                        '*' => ['user_id', 'status', 'transaction_id', 'amount'],
                    ],
                ],
            ]);

        expect($response->json('data.success_count'))->toBe(3)
            ->and($response->json('data.failure_count'))->toBe(0);

        foreach ($userIds as $userId) {
            $this->assertDatabaseHas('wallet_transactions', [
                'user_id'     => $userId,
                'type'        => 'gift',
                'amount'      => $this->campaign->amount,
                'source_type' => TransactionSourceEnum::CAMPAIGN->value,
                'source_id'   => $this->campaign->id,
            ]);
        }

        expect($this->campaign->fresh()->total_usage_count)->toBe(3);
    });

    it('repeats a manual grant for the same user', function (): void {
        $this->campaign->update(['usage_limit_per_user' => 5]);

        $userIds = [$this->users->first()->id];

        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => $userIds,
                'reason'   => 'First grant',
            ])
            ->assertOk();

        $this->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
            'user_ids' => $userIds,
            'reason'   => 'Second grant',
        ])->assertOk();

        expect($this->campaign->fresh()->total_usage_count)->toBe(2);
    });

    it('replays a request with the same Idempotency-Key without double crediting', function (): void {
        $userIds = $this->users->pluck('id')->toArray();
        $payload = [
            'user_ids' => $userIds,
            'reason'   => 'Retry-safe grant',
        ];
        $headers = ['Idempotency-Key' => 'bulk-submit-uuid'];

        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), $payload, $headers)
            ->assertOk();

        $this->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), $payload, $headers)
            ->assertOk();

        expect($this->campaign->fresh()->total_usage_count)->toBe(3);
        expect(WalletTransaction::query()->where('source_id', $this->campaign->id)->count())->toBe(3);
    });

    it('rejects a campaign that is not a manual allocation campaign', function (): void {
        $this->campaign->update(['type' => CampaignTypeEnum::REGISTRATION_BONUS->value]);

        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => [$this->users->first()->id],
                'reason'   => 'Wrong campaign type',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', __('validation.custom.campaign_not_manual_allocation'));
    });

    it('handles partial failures gracefully', function (): void {
        $existingUser = $this->users->first();

        $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => [$existingUser->id],
                'reason'   => 'Pre-existing allocation',
            ])
            ->assertOk();

        $response = $this->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
            'user_ids' => $this->users->pluck('id')->toArray(),
            'reason'   => 'Bulk allocation with a limit conflict',
        ]);

        $response->assertStatus(207);

        expect($response->json('data.success_count'))->toBe(2)
            ->and($response->json('data.failure_count'))->toBe(1);

        $failed = collect($response->json('data.results'))->firstWhere('status', 'failed');
        expect($failed['user_id'])->toBe($existingUser->id);
    });

    it('requires a reason', function (): void {
        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => $this->users->pluck('id')->toArray(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });

    it('requires user_ids field', function (): void {
        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'reason' => 'Bulk allocation',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    });

    it('requires at least one user_id', function (): void {
        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => [],
                'reason'   => 'Bulk allocation',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    });

    it('limits allocations to maximum users', function (): void {
        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => range(1, 101),
                'reason'   => 'Too many users',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    });

    it('validates that all user_ids exist', function (): void {
        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => [999999, 999998],
                'reason'   => 'Invalid users',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids.0', 'user_ids.1']);
    });

    it('rejects an oversized Idempotency-Key header', function (): void {
        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(
                route('api.v1.admin.wallet-campaigns.allocations', $this->campaign),
                ['user_ids'        => [$this->users->first()->id], 'reason' => 'Key too long'],
                ['Idempotency-Key' => str_repeat('a', 201)]
            );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    });

    it('requires permission to allocate campaigns', function (): void {
        $response = $this->authorized_user([])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => $this->users->pluck('id')->toArray(),
                'reason'   => 'Test allocation',
            ]);

        $response->assertForbidden();
    });

    it('handles campaign that is inactive', function (): void {
        $this->campaign->update(['is_active' => false]);

        $response = $this->authorized_user([PermissionEnum::WALLET_CAMPAIGN_ALLOCATE])
            ->postJson(route('api.v1.admin.wallet-campaigns.allocations', $this->campaign), [
                'user_ids' => $this->users->pluck('id')->toArray(),
                'reason'   => 'Test allocation',
            ]);

        $response->assertStatus(207);

        expect($response->json('data.success_count'))->toBe(0)
            ->and($response->json('data.failure_count'))->toBe(3);
    });
});
