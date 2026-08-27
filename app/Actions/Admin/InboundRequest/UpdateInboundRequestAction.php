<?php

declare(strict_types=1);

namespace App\Actions\Admin\InboundRequest;

use App\Models\CollaborationRequest;
use App\Models\ContactUsRequest;
use App\Models\Staff;
use App\Notifications\Admin\InboundRequestAssignedNotification;

final class UpdateInboundRequestAction
{
    /** @param array{status?: mixed, note?: ?string, assigned_to_id?: ?int} $attributes */
    public function handle(ContactUsRequest|CollaborationRequest $request, array $attributes, ?Staff $actor = null): ContactUsRequest|CollaborationRequest
    {
        $previousAssigneeId = $request->assigned_to_id;
        $nextAssigneeId     = $attributes['assigned_to_id'] ?? $previousAssigneeId;
        $request->update($attributes);
        $updatedRequest = $request->fresh('assignee');

        if (array_key_exists('assigned_to_id', $attributes)
            && $nextAssigneeId       !== null
            && (int) $nextAssigneeId !== (int) $previousAssigneeId
            && ($actor === null || (int) $nextAssigneeId !== $actor->id)) {
            Staff::query()->findOrFail($nextAssigneeId)->notify(new InboundRequestAssignedNotification($updatedRequest));
        }

        return $updatedRequest;
    }
}
