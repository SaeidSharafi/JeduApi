<?php

declare(strict_types=1);

namespace App\Actions\Admin\InboundRequest;

use App\Models\CollaborationRequest;
use App\Models\ContactUsRequest;

final class UpdateInboundRequestAction
{
    /** @param array{status?: mixed, note?: ?string, assigned_to_id?: ?int} $attributes */
    public function handle(ContactUsRequest|CollaborationRequest $request, array $attributes): ContactUsRequest|CollaborationRequest
    {
        $request->update($attributes);

        return $request->fresh('assignee');
    }
}
