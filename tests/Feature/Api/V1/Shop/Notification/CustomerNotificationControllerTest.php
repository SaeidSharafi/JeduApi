<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

uses(Tests\Support\Traits\AuthTestTrait::class);

function createCustomerNotification(User $user, array $data, ?DateTimeInterface $readAt = null): DatabaseNotification
{
    return $user->notifications()->create([
        'id'      => (string) Str::uuid(),
        'type'    => 'App\\Notifications\\Wallet\\WalletGiftAllocatedNotification',
        'data'    => $data,
        'read_at' => $readAt,
    ]);
}

it('lists only the authenticated customer notifications and filters unread', function (): void {
    $this->customer();
    $ownUnread = createCustomerNotification($this->user, [
        'type'          => 'wallet_gift_allocated',
        'title'         => 'Wallet gift credited',
        'message'       => 'A gift was added to your wallet.',
        'resource_type' => 'wallet_transaction',
        'resource_id'   => 10,
    ]);
    createCustomerNotification($this->user, [
        'title'   => 'Read notice',
        'message' => 'Already read.',
    ], now());
    createCustomerNotification(User::factory()->create(), [
        'title'   => 'Private notice',
        'message' => 'Not yours.',
    ]);

    $response = $this->getJson('/api/v1/shop/notifications?filter[unread]=true');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [['id', 'type', 'title', 'message', 'resource_type', 'resource_id', 'read_at', 'created_at']],
            ],
        ])
        ->assertJsonPath('data.data.0.id', $ownUnread->id)
        ->assertJsonPath('data.data.0.type', 'wallet_gift_allocated');
    expect($response->json('data.total'))->toBe(1);
});

it('returns the public payload type, never the notification class name', function (): void {
    $this->customer();
    createCustomerNotification($this->user, [
        'title'   => 'Stored without a public type',
        'message' => 'Legacy row.',
    ]);

    $this->getJson(route('api.v1.shop.notifications.index'))
        ->assertOk()
        ->assertJsonPath('data.data.0.type', null);
});

it('returns the unread count for the authenticated customer', function (): void {
    $this->customer();
    createCustomerNotification($this->user, ['title' => 'Unread', 'message' => 'Unread.']);
    createCustomerNotification($this->user, ['title' => 'Read', 'message' => 'Read.'], now());
    createCustomerNotification(User::factory()->create(), ['title' => 'Other', 'message' => 'Other.']);

    $this->getJson(route('api.v1.shop.notifications.unread-count'))
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('marks only the customer own notifications as read', function (): void {
    $this->customer();
    $own   = createCustomerNotification($this->user, ['title' => 'Own', 'message' => 'Own.']);
    $other = createCustomerNotification(User::factory()->create(), ['title' => 'Other', 'message' => 'Other.']);

    $this->patchJson("/api/v1/shop/notifications/{$other->id}/read")->assertNotFound();
    $this->patchJson(route('api.v1.shop.notifications.read', $own->id))->assertNoContent();

    expect($own->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh()->read_at)->toBeNull();
});

it('marks all of the customer unread notifications as read', function (): void {
    $this->customer();
    $own   = createCustomerNotification($this->user, ['title' => 'Own', 'message' => 'Own.']);
    $other = createCustomerNotification(User::factory()->create(), ['title' => 'Other', 'message' => 'Other.']);

    $this->patchJson(route('api.v1.shop.notifications.read-all'))->assertNoContent();

    expect($own->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh()->read_at)->toBeNull();
});

it('requires customer authentication', function (): void {
    $this->getJson('/api/v1/shop/notifications')->assertUnauthorized();
});
