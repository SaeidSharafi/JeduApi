<?php

declare(strict_types=1);

namespace App\Actions\Shop\Forms;

use App\Actions\Shop\UploadFileAction;
use App\Data\Shop\Forms\CreateCollaborationRequestData;
use App\Models\CollaborationRequest;

final readonly class CreateCollaborationRequestAction
{
    public function __construct(
        private UploadFileAction $action,
    ) {}

    public function handle(CreateCollaborationRequestData $data): void
    {
        $collaborationRequest = CollaborationRequest::query()
            ->create($data->except('attachment')->toArray());

        if ($data->attachment) {
            $attachment = $this->action->handle($data->attachment, false);
            $collaborationRequest->attachMedia($attachment, 'attachment');
        }
    }
}
