<?php

declare(strict_types=1);

use App\Enums\InboundRequestStatusEnum;
use App\Models\OrganizationTrainingRequest;
use App\Models\Staff;
use App\Models\Vendor;

it('serializes organization training request fields', function (): void {
    $request = OrganizationTrainingRequest::factory()->create([
        'vendor_snapshot' => ['id' => 12, 'name' => 'Department'],
    ])->fresh();

    expect($request->toArray())->toMatchArray([
        'id'                     => $request->id,
        'uuid'                   => $request->uuid,
        'first_name'             => $request->first_name,
        'last_name'              => $request->last_name,
        'phone'                  => $request->phone,
        'position'               => $request->position,
        'organization_name'      => $request->organization_name,
        'requested_course_names' => $request->requested_course_names,
        'notes'                  => $request->notes,
        'status'                 => InboundRequestStatusEnum::PENDING->value,
        'assigned_to_id'         => null,
        'vendor_id'              => null,
        'vendor_snapshot'        => ['id' => 12, 'name' => 'Department'],
    ]);
});

it('belongs to an assignee', function (): void {
    $assignee = Staff::factory()->create();
    $request  = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => $assignee->id]);

    expect($request->assignee)->toBeInstanceOf(Staff::class)->id->toBe($assignee->id);
});

it('belongs to a vendor', function (): void {
    $vendor  = Vendor::factory()->create();
    $request = OrganizationTrainingRequest::factory()->create(['vendor_id' => $vendor->id]);

    expect($request->vendor)->toBeInstanceOf(Vendor::class)->id->toBe($vendor->id);
});
