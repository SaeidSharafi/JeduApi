<?php

declare(strict_types=1);

use App\Enums\InboundRequestStatusEnum;
use App\Models\OrganizationTrainingRequest;
use App\Models\Staff;

it('serializes organization training request fields', function (): void {
    $request = OrganizationTrainingRequest::factory()->create()->fresh();

    expect($request->toArray())->toMatchArray([
        'id'                     => $request->id,
        'tracking_code'          => $request->tracking_code,
        'first_name'             => $request->first_name,
        'last_name'              => $request->last_name,
        'phone'                  => $request->phone,
        'position'               => $request->position,
        'organization_name'      => $request->organization_name,
        'requested_course_names' => $request->requested_course_names,
        'notes'                  => $request->notes,
        'status'                 => InboundRequestStatusEnum::PENDING->value,
        'assigned_to_id'         => null,
    ]);
});

it('belongs to an assignee', function (): void {
    $assignee = Staff::factory()->create();
    $request  = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => $assignee->id]);

    expect($request->assignee)->toBeInstanceOf(Staff::class)->id->toBe($assignee->id);
});
