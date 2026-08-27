<?php

declare(strict_types=1);

namespace App\Actions\Admin\ContactRequest;

use App\Models\ContactUsRequest;

final class UpdateContactRequestAction
{
    /** @param array{status?: mixed, note?: ?string, assigned_to_id?: ?int} $attributes */
    public function handle(ContactUsRequest $request, array $attributes): ContactUsRequest
    {
        $request->update($attributes);

        return $request->fresh('assignee');
    }
}
