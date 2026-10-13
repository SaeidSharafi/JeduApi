<?php

declare(strict_types=1);

use App\Actions\Admin\Discounts\GrantOrderRewardsAction;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;

covers(GrantOrderRewardsAction::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function grantedRewardIntent(array $overrides = []): array
{
    return array_merge([
        'promotion_id'   => 4242,
        'promotion_name' => 'Reward Promotion',
        'type'           => TransactionTypeEnum::BONUS->value,
        'amount'         => 5000,
        'description'    => 'Wallet bonus',
        'configuration'  => ['amount' => 5000, 'per_item' => false],
        'expires_days'   => null,
    ], $overrides);
}

it('grants an order reward exactly once, however often settlement runs', function (): void {
    $customer = User::factory()->create();
    $order    = Order::factory()->create([
        'customer_id'         => $customer->id,
        'reward_intents_json' => [grantedRewardIntent()],
    ]);

    $action = app(GrantOrderRewardsAction::class);
    $action->handle($order);
    $action->handle($order->fresh());

    $transactions = WalletTransaction::query()->where('user_id', $customer->id)->get();

    expect($transactions)->toHaveCount(1);

    $transaction = $transactions->first();
    expect($transaction->amount)->toBe(5000)
        ->and($transaction->type)->toBe(TransactionTypeEnum::BONUS)
        ->and($transaction->source_type)->toBe(TransactionSourceEnum::PROMOTION)
        ->and($transaction->source_id)->toBe(4242)
        ->and($transaction->description)->toBe('Wallet bonus')
        ->and($transaction->metadata['order_id'])->toBe($order->id)
        ->and($transaction->metadata['promotion_name'])->toBe('Reward Promotion')
        ->and($transaction->metadata['credit_type'])->toBe('regular');

    expect($customer->wallet->refresh()->gift_balance)->toBe(5000);
});

it('grants two rewards from the same promotion and credit type as separate credits', function (): void {
    $customer = User::factory()->create();
    $order    = Order::factory()->create([
        'customer_id'         => $customer->id,
        'reward_intents_json' => [
            grantedRewardIntent(['amount' => 5000, 'description' => 'First bonus']),
            grantedRewardIntent(['amount' => 2000, 'description' => 'Second bonus']),
        ],
    ]);

    app(GrantOrderRewardsAction::class)->handle($order);

    $transactions = WalletTransaction::query()->where('user_id', $customer->id)->get();

    expect($transactions)->toHaveCount(2)
        ->and($transactions->sum('amount'))->toBe(7000);

    expect($customer->wallet->refresh()->gift_balance)->toBe(7000);
});

it('skips the reward without failing when the customer has no wallet', function (): void {
    $customer = User::factory()->create();
    $customer->wallet->delete();
    $customer->refresh();

    $order = Order::factory()->create([
        'customer_id'         => $customer->id,
        'reward_intents_json' => [grantedRewardIntent()],
    ]);

    expect(fn () => app(GrantOrderRewardsAction::class)->handle($order))->not->toThrow(Exception::class);

    expect(WalletTransaction::query()->where('user_id', $customer->id)->count())->toBe(0);
});

it('computes gift expiry from the grant date rather than the order date', function (): void {
    Carbon::setTestNow('2026-01-01 10:00:00');

    $customer = User::factory()->create();
    $order    = Order::factory()->create([
        'customer_id'         => $customer->id,
        'reward_intents_json' => [
            grantedRewardIntent([
                'type'         => TransactionTypeEnum::GIFT->value,
                'expires_days' => 30,
            ]),
        ],
    ]);

    // The order sat pending for a month before it was paid for.
    Carbon::setTestNow('2026-02-01 10:00:00');

    app(GrantOrderRewardsAction::class)->handle($order);

    $transaction = WalletTransaction::query()->where('user_id', $customer->id)->firstOrFail();

    expect($transaction->type)->toBe(TransactionTypeEnum::GIFT)
        ->and($transaction->expires_at->toDateTimeString())->toBe('2026-03-03 10:00:00')
        ->and($transaction->metadata['credit_type'])->toBe('gift');

    Carbon::setTestNow();
});
