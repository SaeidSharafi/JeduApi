<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\OrganizationTrainingRequest;
use App\Models\Staff;
use App\Policies\Admin\OrganizationTrainingRequestPolicy;

describe('OrganizationTrainingRequestPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new OrganizationTrainingRequestPolicy();
    });

    it('requires view any permission for listing', function (): void {
        $allowed = Staff::factory()->create();
        $allowed->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_VIEW_ANY->value);

        expect($this->policy->viewAny($allowed->fresh()))->toBeTrue()
            ->and($this->policy->viewAny(Staff::factory()->create()))->toBeFalse();
    });

    it('requires view permission for showing a request', function (): void {
        $request = OrganizationTrainingRequest::factory()->create();
        $allowed = Staff::factory()->create();
        $allowed->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_VIEW->value);

        expect($this->policy->view($allowed->fresh(), $request))->toBeTrue()
            ->and($this->policy->view(Staff::factory()->create(), $request))->toBeFalse();
    });

    it('allows update permission on every request', function (): void {
        $staff = Staff::factory()->create();
        $staff->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE->value);

        expect($this->policy->update($staff->fresh(), OrganizationTrainingRequest::factory()->create()))->toBeTrue();
    });

    it('allows update-own only on an assigned request', function (): void {
        $staff = Staff::factory()->create();
        $staff->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE_OWN->value);
        $assigned   = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => $staff->id]);
        $unassigned = OrganizationTrainingRequest::factory()->create();
        $other      = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => Staff::factory()]);

        expect($this->policy->update($staff->fresh(), $assigned))->toBeTrue()
            ->and($this->policy->update($staff, $unassigned))->toBeFalse()
            ->and($this->policy->update($staff, $other))->toBeFalse();
    });

    it('denies update without update permission', function (): void {
        expect($this->policy->update(Staff::factory()->create(), OrganizationTrainingRequest::factory()->create()))->toBeFalse();
    });

    it('allows update permission to assign any staff or unassign', function (): void {
        $staff    = Staff::factory()->create();
        $assignee = Staff::factory()->create();
        $staff->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE->value);
        $request = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => $assignee->id]);

        expect($this->policy->assign($staff->fresh(), $request, Staff::factory()->create()))->toBeTrue()
            ->and($this->policy->assign($staff, $request, null))->toBeTrue();
    });

    it('allows update-own to claim, keep, or release its own request', function (): void {
        $staff = Staff::factory()->create();
        $staff->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE_OWN->value);
        $unassigned = OrganizationTrainingRequest::factory()->create();
        $assigned   = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => $staff->id]);

        expect($this->policy->assign($staff->fresh(), $unassigned, $staff))->toBeTrue()
            ->and($this->policy->assign($staff, $assigned, $staff))->toBeTrue()
            ->and($this->policy->assign($staff, $assigned, null))->toBeTrue();
    });

    it('denies update-own assignment outside its allowed transitions', function (): void {
        $staff        = Staff::factory()->create();
        $otherStaff   = Staff::factory()->create();
        $otherRequest = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => $otherStaff->id]);
        $unassigned   = OrganizationTrainingRequest::factory()->create();
        $assigned     = OrganizationTrainingRequest::factory()->create(['assigned_to_id' => $staff->id]);
        $staff->givePermissionTo(PermissionEnum::ORGANIZATION_TRAINING_REQUEST_UPDATE_OWN->value);

        expect($this->policy->assign($staff->fresh(), $unassigned, $otherStaff))->toBeFalse()
            ->and($this->policy->assign($staff, $otherRequest, $staff))->toBeFalse()
            ->and($this->policy->assign($staff, $assigned, $otherStaff))->toBeFalse()
            ->and($this->policy->assign(Staff::factory()->create(), $unassigned, $staff))->toBeFalse();
    });
});
