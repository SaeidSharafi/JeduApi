<?php

declare(strict_types=1);

use App\Enums\AdviceRequestStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\AdviceRequest;

uses(Tests\Support\Traits\AuthTestTrait::class);
describe('AdviceRequestController', function (): void {
    it('list requests and filter them', function (): void {
        $this->authorized_user([PermissionEnum::ADVICE_REQUEST_VIEW_ANY]);
        AdviceRequest::factory()->count(4)
            ->create(['status' => AdviceRequestStatusEnum::PENDING]);
        AdviceRequest::factory()->count(3)
            ->create(['status' => AdviceRequestStatusEnum::CONTACTED]);
        AdviceRequest::factory()->count(2)
            ->create(['status' => AdviceRequestStatusEnum::RESOLVED]);
        AdviceRequest::factory()->count(4)
            ->create(['status' => AdviceRequestStatusEnum::NO_RESPONSE]);

        $staff = App\Models\Staff::factory()->create();
        AdviceRequest::factory()->count(2)
            ->create([
                'status'        => AdviceRequestStatusEnum::CONTACTED,
                'handled_by_id' => $staff->id,
            ]);
        $response = $this->getJson('/api/v1/admin/advice-requests');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'phone',
                        'status',
                        'note',
                        'handler',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
        $responseData = $response->json('data.data');
        $this->assertCount(15, $responseData);
        $response = $this->getJson('/api/v1/admin/advice-requests?filter[status]=contacted');
        $response->assertOk();
        $responseData = $response->json('data.data');
        $this->assertCount(5, $responseData);
        foreach ($responseData as $item) {
            $this->assertEquals(AdviceRequestStatusEnum::CONTACTED->value, $item['status']['value']);
        }
        $response = $this->getJson('/api/v1/admin/advice-requests?filter[handled_by_id]='.$staff->id);
        $response->assertOk();
        $responseData = $response->json('data.data');
        $this->assertCount(2, $responseData);
        foreach ($responseData as $item) {
            $this->assertEquals($staff->id, $item['handler']['id']);
        }
    });

    it('view a specific advice request', function (): void {
        $this->authorized_user([PermissionEnum::ADVICE_REQUEST_VIEW]);
        $request = AdviceRequest::factory()->create([
            'status' => AdviceRequestStatusEnum::PENDING,
        ]);
        $response = $this->getJson('/api/v1/admin/advice-requests/'.$request->id);
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'phone',
                'status',
                'note',
                'handler',
                'created_at',
                'updated_at',
            ],
        ]);
        $responseData = $response->json('data');
        $this->assertEquals($request->id, $responseData['id']);
        $this->assertEquals($request->phone, $responseData['phone']);
        $this->assertEquals($request->status->value, $responseData['status']['value']);
    });

    it('update a specific advice request', function (): void {
        $this->authorized_user([PermissionEnum::ADVICE_REQUEST_UPDATE]);
        $request = AdviceRequest::factory()->create([
            'status' => AdviceRequestStatusEnum::PENDING,
        ]);
        $payload = [
            'status' => AdviceRequestStatusEnum::CONTACTED->value,
            'note'   => 'This is a note',
        ];
        $response = $this->putJson('/api/v1/admin/advice-requests/'.$request->id, $payload);
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'phone',
                'status',
                'note',
                'handler',
                'created_at',
                'updated_at',
            ],
        ]);
        $responseData = $response->json('data');

        $this->assertEquals($request->id, $responseData['id']);
        $this->assertEquals($request->phone, $responseData['phone']);
        $this->assertEquals($payload['status'], $responseData['status']['value']);
        $this->assertEquals($payload['note'], $responseData['note']);
        $this->assertEquals($this->user->id, $responseData['handler']['id']);

        $this->assertDatabaseHas('advice_requests', [
            'id'            => $request->id,
            'status'        => $payload['status'],
            'note'          => $payload['note'],
            'handled_by_id' => $this->user->id,
        ]);
    });

    it('delete a specific advice request', function (): void {
        $this->authorized_user([PermissionEnum::ADVICE_REQUEST_DELETE]);
        $request = AdviceRequest::factory()->create([
            'status' => AdviceRequestStatusEnum::PENDING,
        ]);
        $response = $this->deleteJson('/api/v1/admin/advice-requests/'.$request->id);
        $response->assertNoContent();
        $this->assertDatabaseMissing('advice_requests', [
            'id' => $request->id,
        ]);
    });

    it('forbid unauthorized access', function (): void {
        $this->unauthorized_user();
        // List
        $response = $this->getJson('/api/v1/admin/advice-requests');
        $response->assertForbidden();
        // View
        $request  = AdviceRequest::factory()->create();
        $response = $this->getJson('/api/v1/admin/advice-requests/'.$request->id);
        $response->assertForbidden();
        // Update
        $response = $this->putJson('/api/v1/admin/advice-requests/'.$request->id, [
            'status' => AdviceRequestStatusEnum::CONTACTED->value,
        ]);
        $response->assertForbidden();
        // Delete
        $response = $this->deleteJson('/api/v1/admin/advice-requests/'.$request->id);
        $response->assertForbidden();
    });
});

describe('AdviceRequestUpdateStatusController', function (): void {
    it('update status of a specific advice request', function (): void {
        $this->authorized_user([PermissionEnum::ADVICE_REQUEST_UPDATE]);
        $request = AdviceRequest::factory()->create([
            'status' => AdviceRequestStatusEnum::PENDING,
        ]);
        $payload = [
            'status' => AdviceRequestStatusEnum::CONTACTED->value,
        ];
        $response = $this->patchJson('/api/v1/admin/advice-requests/'.$request->id.'/status', $payload);
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'phone',
                'status',
                'note',
                'handler',
                'created_at',
                'updated_at',
            ],
        ]);
        $responseData = $response->json('data');

        $this->assertEquals($request->id, $responseData['id']);
        $this->assertEquals($request->phone, $responseData['phone']);
        $this->assertEquals($payload['status'], $responseData['status']['value']);
        $this->assertEquals($this->user->id, $responseData['handler']['id']);

        $this->assertDatabaseHas('advice_requests', [
            'id'            => $request->id,
            'status'        => $payload['status'],
            'handled_by_id' => $this->user->id,
        ]);
    });

    it('forbid unauthorized access', function (): void {
        $this->unauthorized_user();
        $request  = AdviceRequest::factory()->create();
        $response = $this->patchJson('/api/v1/admin/advice-requests/'.$request->id.'/status', [
            'status' => AdviceRequestStatusEnum::CONTACTED->value,
        ]);
        $response->assertForbidden();
    });
});
