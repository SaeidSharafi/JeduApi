<?php

declare(strict_types=1);

use App\Enums\InboundRequestStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\CollaborationRequest;
use App\Models\Staff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('lists and filters collaboration requests', function (): void {
    $this->authorized_user([PermissionEnum::COLLABORATION_REQUEST_VIEW_ANY]);
    CollaborationRequest::factory()->create(['department' => 'partnerships', 'status' => InboundRequestStatusEnum::PENDING]);
    CollaborationRequest::factory()->create(['department' => 'sales', 'status' => InboundRequestStatusEnum::RESOLVED]);

    $response = $this->getJson('/api/v1/admin/collaboration-requests?filter[department]=partnerships&filter[search]=partnerships');

    $response->assertOk()->assertJsonStructure(['data' => ['data' => ['*' => ['id', 'full_name', 'department', 'status', 'assignee', 'has_attachment', 'created_at']]]]);
    expect($response->json('data.data'))->toHaveCount(1);
});

it('shows collaboration details and attachment metadata', function (): void {
    Storage::fake('local');
    $this->postJson(route('api.v1.shop.collaboration.store'), [
        'full_name' => 'Jane Doe', 'phone' => '09123456789', 'email' => 'jane@example.com',
        'message'   => 'Let us collaborate.', 'attachment' => UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
    ])->assertCreated();
    $this->authorized_user([PermissionEnum::COLLABORATION_REQUEST_VIEW]);
    $request = CollaborationRequest::firstOrFail();

    $response = $this->getJson('/api/v1/admin/collaboration-requests/'.$request->id);

    $response->assertOk()->assertJsonPath('data.attachment.extension', 'pdf')->assertJsonPath('data.full_name', 'Jane Doe');
});

it('updates collaboration status assignment and note independently', function (): void {
    $this->authorized_user([PermissionEnum::COLLABORATION_REQUEST_UPDATE]);
    $request  = CollaborationRequest::factory()->create();
    $assignee = Staff::factory()->create();

    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/status", ['status' => 'contacted'])->assertOk();
    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/assignment", ['staff_id' => $assignee->id])->assertOk();
    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/note", ['note' => 'Follow up'])->assertOk();

    $this->assertDatabaseHas('collaboration_requests', ['id' => $request->id, 'status' => 'contacted', 'assigned_to_id' => $assignee->id, 'note' => 'Follow up']);
});

it('allows update-own staff to claim and work only their collaboration request', function (): void {
    $this->authorized_user([PermissionEnum::COLLABORATION_REQUEST_UPDATE_OWN]);
    $request = CollaborationRequest::factory()->create();
    $other   = CollaborationRequest::factory()->create(['assigned_to_id' => Staff::factory()]);

    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/assignment", ['staff_id' => $this->user->id])->assertOk();
    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/status", ['status' => 'resolved'])->assertOk();
    $this->patchJson("/api/v1/admin/collaboration-requests/{$other->id}/status", ['status' => 'resolved'])->assertForbidden();
});

it('rejects banned collaboration assignees and unauthorized access', function (): void {
    $this->authorized_user([PermissionEnum::COLLABORATION_REQUEST_UPDATE]);
    $request = CollaborationRequest::factory()->create();
    $banned  = Staff::factory()->create(['is_banned' => true]);

    $this->patchJson("/api/v1/admin/collaboration-requests/{$request->id}/assignment", ['staff_id' => $banned->id])->assertUnprocessable();

    $this->unauthorized_user();
    $this->getJson('/api/v1/admin/collaboration-requests')->assertForbidden();
});
