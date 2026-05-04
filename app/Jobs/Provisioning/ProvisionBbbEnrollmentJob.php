<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use App\Services\Integrations\BbbService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class ProvisionBbbEnrollmentJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesProvisioningStatus;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $enrollmentId) {}

    public function handle(BbbService $bbbService): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $details   = $enrollment->productDeliveryOption?->details_json ?? [];
        $meetingId = data_get($details, 'meeting_id');
        if (! is_string($meetingId) || $meetingId === '') {
            throw new RuntimeException('BBB meeting_id is missing from delivery option details.');
        }

        $autoCreateMeeting = (bool) data_get($details, 'auto_create_meeting', false);
        $attendeePassword  = (string) (data_get($details, 'attendee_password') ?: config('services.bbb.default_attendee_password', 'ap'));
        $moderatorPassword = (string) (data_get($details, 'moderator_password') ?: config('services.bbb.default_moderator_password', 'mp'));

        if ($autoCreateMeeting) {
            $bbbService->createMeeting(
                $meetingId,
                $enrollment->productDeliveryOption?->name ?? ('meeting-'.$meetingId),
                $attendeePassword,
                $moderatorPassword,
            );
        }

        $fullName = trim(($enrollment->customer->first_name ?? '').' '.($enrollment->customer->last_name ?? ''));
        $joinUrl  = $bbbService->buildJoinUrl($meetingId, $fullName ?: 'Student', $attendeePassword);

        $this->markProvisioningSuccess($enrollment, 'bbb', [
            'meeting_id'          => $meetingId,
            'auto_create_meeting' => $autoCreateMeeting,
            'attendee_join_url'   => $joinUrl,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $this->markProvisioningFailure($enrollment, 'bbb', $exception->getMessage());
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    private function findEnrollment(): ?Enrollment
    {
        return Enrollment::query()
            ->with(['customer', 'productDeliveryOption'])
            ->find($this->enrollmentId);
    }
}
