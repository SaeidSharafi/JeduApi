<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\CollaborationRequest;
use App\Models\ContactUsRequest;
use App\Models\OrganizationTrainingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class InboundRequestAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ContactUsRequest|CollaborationRequest|OrganizationTrainingRequest $request)
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
        $resourceType = match (true) {
            $this->request instanceof CollaborationRequest        => 'collaboration_request',
            $this->request instanceof OrganizationTrainingRequest => 'organization_training_request',
            default                                               => 'contact_request',
        };
        $label = match (true) {
            $this->request instanceof CollaborationRequest        => 'collaboration request',
            $this->request instanceof OrganizationTrainingRequest => 'organization training request',
            default                                               => 'contact request',
        };

        return [
            'title'         => 'New inbound request assignment',
            'message'       => "A {$label} was assigned to you.",
            'resource_type' => $resourceType,
            'resource_id'   => $this->request->id,
        ];
    }
}
