<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\OrganizationTrainingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class OrganizationTrainingRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrganizationTrainingRequest $request)
    {
        $this->onQueue('notifications');
    }

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, message: string, resource_type: string, resource_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'         => 'New Organization Training Request',
            'message'       => 'A new organization training request was submitted.',
            'resource_type' => 'organization_training_request',
            'resource_id'   => $this->request->id,
        ];
    }
}
