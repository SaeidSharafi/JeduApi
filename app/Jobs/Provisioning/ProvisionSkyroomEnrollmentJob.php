<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\System\SettingKeyEnum;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use App\Services\Integrations\SkyroomService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class ProvisionSkyroomEnrollmentJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesProvisioningStatus;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $enrollmentId) {}

    public function handle(SkyroomService $skyroomService, SettingsService $settings): void
    {
        $config = $settings->get(SettingKeyEnum::SKYROOM);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        if (empty($config['api_key'])) {
            throw new RuntimeException('Skyroom configuration is missing api_key.');
        }

        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $details = $enrollment->productDeliveryOption?->details_json ?? [];
        $roomId  = data_get($details, 'room_id');

        if (! is_numeric($roomId)) {
            throw new RuntimeException('Skyroom room_id is missing from delivery option details.');
        }
        $roomId = (int) $roomId;

        $result        = $skyroomService->findOrCreateUser($enrollment->customer);
        $skyroomUserId = $result['skyroom_user_id'];
        $skyroomService->addUserToRoom($roomId, $skyroomUserId);

        $this->markProvisioningSuccess($enrollment, 'skyroom', [
            'room_id'         => $roomId,
            'skyroom_user_id' => $skyroomUserId,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }
        $this->markProvisioningFailure($enrollment, 'skyroom', $exception->getMessage());
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
