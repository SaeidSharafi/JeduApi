<?php

declare(strict_types=1);

use App\Enums\InboundRequestStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\ContactUsRequest;
use App\Models\Staff;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('lists and filters contact requests', function (): void {
    $this->authorized_user([PermissionEnum::CONTACT_US_REQUEST_VIEW_ANY]);
    ContactUsRequest::factory()->create(['subject' => 'Course question', 'status' => InboundRequestStatusEnum::PENDING]);
    ContactUsRequest::factory()->create(['subject' => 'Billing question', 'status' => InboundRequestStatusEnum::RESOLVED]);

    $response = $this->getJson('/api/v1/admin/contact-requests?filter[status]=pending&filter[search]=Course');

    $response->assertOk()->assertJsonStructure(['data' => ['data' => ['*' => ['id', 'full_name', 'subject', 'status', 'assignee', 'created_at']]]]);
    expect($response->json('data.data'))->toHaveCount(1);
});

it('shows a contact request', function (): void {
    $this->authorized_user([PermissionEnum::CONTACT_US_REQUEST_VIEW]);
    $request = ContactUsRequest::factory()->create(['note' => 'Call tomorrow']);

    $response = $this->getJson('/api/v1/admin/contact-requests/'.$request->id);

    $response->assertOk()->assertJsonPath('data.id', $request->id)->assertJsonPath('data.note', 'Call tomorrow');
});

it('updates status assignment and note independently', function (): void {
    $this->authorized_user([
        PermissionEnum::CONTACT_US_REQUEST_UPDATE,
        PermissionEnum::CONTACT_US_REQUEST_VIEW,
    ]);
    $request  = ContactUsRequest::factory()->create();
    $assignee = Staff::factory()->create();

    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/status", ['status' => 'contacted'])->assertOk();
    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignment", ['staff_id' => $assignee->id])->assertOk();
    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/note", ['note' => 'Follow up'])->assertOk();

    $this->assertDatabaseHas('contact_us_requests', [
        'id'             => $request->id,
        'status'         => 'contacted',
        'assigned_to_id' => $assignee->id,
        'note'           => 'Follow up',
    ]);
});

it('allows update-own staff to claim and work only their request', function (): void {
    $this->authorized_user([PermissionEnum::CONTACT_US_REQUEST_UPDATE_OWN]);
    $request = ContactUsRequest::factory()->create();
    $other   = ContactUsRequest::factory()->create(['assigned_to_id' => Staff::factory()]);

    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignment", ['staff_id' => $this->user->id])->assertOk();
    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/status", ['status' => 'resolved'])->assertOk();
    $this->patchJson("/api/v1/admin/contact-requests/{$other->id}/status", ['status' => 'resolved'])->assertForbidden();
});

it('rejects banned assignees and unauthorized access', function (): void {
    $this->authorized_user([PermissionEnum::CONTACT_US_REQUEST_UPDATE]);
    $request = ContactUsRequest::factory()->create();
    $banned  = Staff::factory()->create(['is_banned' => true]);

    $this->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignment", ['staff_id' => $banned->id])->assertUnprocessable();

    $this->unauthorized_user();
    $this->getJson('/api/v1/admin/contact-requests')->assertForbidden();
});
