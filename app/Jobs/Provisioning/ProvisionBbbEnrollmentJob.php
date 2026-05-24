<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\System\SettingKeyEnum;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use App\Services\Integrations\BbbService;
use App\Services\SettingsService;
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

    public function handle(BbbService $bbbService, SettingsService $settings): void
    {
        $config = $settings->get(SettingKeyEnum::BIG_BLUE_BUTTON);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        if (empty($config['base_url']) || empty($config['secret'])) {
            throw new RuntimeException('BBB configuration is missing base_url or secret.');
        }

        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $details   = $enrollment->productDeliveryOption?->details_json ?? [];
        $meetingId = data_get($details, 'meeting_id');

        if (! is_string($meetingId) || $meetingId === '') {
            throw new RuntimeException('BBB meeting_id is missing from delivery option details.');
        }

        $autoCreate        = (bool) data_get($details, 'auto_create_meeting', false);
        $attendeePassword  = data_get($details, 'attendee_password');
        $moderatorPassword = data_get($details, 'moderator_password');

        if ($autoCreate) {
            $bbbService->createMeeting(
                meetingId: $meetingId,
                name: $enrollment->productDeliveryOption?->name ?? "meeting-{$meetingId}",
                attendeePw: $attendeePassword,
                moderatorPw: $moderatorPassword,
            );
        }

        $fullName = mb_trim(($enrollment->customer->first_name ?? '').' '.($enrollment->customer->last_name ?? '')) ?: 'Student';
        $joinUrl  = $bbbService->buildJoinUrl($meetingId, $fullName, $attendeePassword);

        $this->markProvisioningSuccess($enrollment, 'bbb', [
            'meeting_id'          => $meetingId,
            'auto_create_meeting' => $autoCreate,
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
