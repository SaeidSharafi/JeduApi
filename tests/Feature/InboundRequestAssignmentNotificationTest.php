<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\CollaborationRequest;
use App\Models\ContactUsRequest;
use App\Models\Staff;
use App\Notifications\Admin\InboundRequestAssignedNotification;
use Illuminate\Support\Facades\Notification;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('notifies a different Contact Request assignee', function (): void {
    $this->authorized_user([PermissionEnum::CONTACT_US_REQUEST_UPDATE]);
    $request  = ContactUsRequest::factory()->create();
    $assignee = Staff::factory()->create();
    Notification::fake();

    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignment", ['staff_id' => $assignee->id])->assertOk();

    Notification::assertSentTo($assignee, InboundRequestAssignedNotification::class, function (InboundRequestAssignedNotification $notification) use ($request, $assignee): bool {
        $payload = $notification->toDatabase($assignee);

        return $payload['resource_type'] === 'contact_request'
            && $payload['resource_id']   === $request->id
            && $payload['title']         === 'New inbound request assignment'
            && $payload['message']       === 'A contact request was assigned to you.';
    });
});

it('notifies a different Collaboration Request assignee', function (): void {
    $this->authorized_user([PermissionEnum::COLLABORATION_REQUEST_UPDATE]);
    $request  = CollaborationRequest::factory()->create();
    $assignee = Staff::factory()->create();
    Notification::fake();

    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/assignment", ['staff_id' => $assignee->id])->assertOk();

    Notification::assertSentTo($assignee, InboundRequestAssignedNotification::class, function (InboundRequestAssignedNotification $notification) use ($request, $assignee): bool {
        $payload = $notification->toDatabase($assignee);

        return $payload['resource_type'] === 'collaboration_request'
            && $payload['resource_id']   === $request->id;
    });
});

it('does not notify on self assignment, unassignment, or unchanged assignment', function (): void {
    $this->authorized_user([PermissionEnum::CONTACT_US_REQUEST_UPDATE]);
    $request = ContactUsRequest::factory()->create();
    $other   = Staff::factory()->create();
    Notification::fake();

    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignment", ['staff_id' => $this->user->id])->assertOk();
    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignment", ['staff_id' => null])->assertOk();
    $request->update(['assigned_to_id' => $other->id]);
    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignment", ['staff_id' => $other->id])->assertOk();

    Notification::assertNothingSent();
});

it('notifies only the new assignee when responsibility is reassigned', function (): void {
    $this->authorized_user([PermissionEnum::COLLABORATION_REQUEST_UPDATE]);
    $previous = Staff::factory()->create();
    $next     = Staff::factory()->create();
    $request  = CollaborationRequest::factory()->create(['assigned_to_id' => $previous->id]);
    Notification::fake();

    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/assignment", ['staff_id' => $next->id])->assertOk();

    Notification::assertSentTo($next, InboundRequestAssignedNotification::class);
    Notification::assertNotSentTo($previous, InboundRequestAssignedNotification::class);
});
