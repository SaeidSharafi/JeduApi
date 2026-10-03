<?php

declare(strict_types=1);

use App\Models\Staff;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

uses(Tests\Support\Traits\AuthTestTrait::class);

function createStaffNotification(Staff $staff, array $data, ?DateTimeInterface $readAt = null): DatabaseNotification
{
    return $staff->notifications()->create([
        'id'      => (string) Str::uuid(),
        'type'    => 'App\\Notifications\\Admin\\InboundRequestAssignedNotification',
        'data'    => $data,
        'read_at' => $readAt,
    ]);
}

it('lists only the authenticated staff notifications and filters unread', function (): void {
    $this->unauthorized_user();
    $ownUnread = createStaffNotification($this->user, [
        'title'         => 'New assignment',
        'message'       => 'A request was assigned to you.',
        'resource_type' => 'contact_request',
        'resource_id'   => 10,
    ]);
    createStaffNotification($this->user, [
        'title'   => 'Read notice',
        'message' => 'Already read.',
    ], now());
    createStaffNotification(Staff::factory()->create(), [
        'title'   => 'Private notice',
        'message' => 'Not yours.',
    ]);

    $response = $this->getJson('/api/v1/admin/notifications?filter[unread]=true');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [['id', 'type', 'title', 'message', 'resource_type', 'resource_id', 'read_at', 'created_at']],
            ],
        ])
        ->assertJsonPath('data.data.0.id', $ownUnread->id);
    expect($response->json('data.total'))->toBe(1);
});

it('returns unread count for the authenticated staff member', function (): void {
    $this->unauthorized_user();
    createStaffNotification($this->user, ['title' => 'Unread', 'message' => 'Unread.']);
    createStaffNotification($this->user, ['title' => 'Read', 'message' => 'Read.'], now());
    createStaffNotification(Staff::factory()->create(), ['title' => 'Other', 'message' => 'Other.']);

    $this->getJson('/api/v1/admin/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('marks only owned notifications as read', function (): void {
    $this->unauthorized_user();
    $own   = createStaffNotification($this->user, ['title' => 'Own', 'message' => 'Own.']);
    $other = createStaffNotification(Staff::factory()->create(), ['title' => 'Other', 'message' => 'Other.']);

    $this->patchJson("/api/v1/admin/notifications/{$other->id}/read")->assertNotFound();
    $this->patchJson("/api/v1/admin/notifications/{$own->id}/read")->assertNoContent();

    expect($own->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh()->read_at)->toBeNull();
});

it('marks all owned unread notifications as read', function (): void {
    $this->unauthorized_user();
    $own   = createStaffNotification($this->user, ['title' => 'Own', 'message' => 'Own.']);
    $other = createStaffNotification(Staff::factory()->create(), ['title' => 'Other', 'message' => 'Other.']);

    $this->patchJson('/api/v1/admin/notifications/read-all')->assertNoContent();

    expect($own->fresh()->read_at)->not->toBeNull()
        ->and($other->fresh()->read_at)->toBeNull();
});

it('requires staff authentication', function (): void {
    $this->getJson('/api/v1/admin/notifications')->assertUnauthorized();
});
