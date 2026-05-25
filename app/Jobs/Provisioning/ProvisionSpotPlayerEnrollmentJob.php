<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\System\SettingKeyEnum;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use App\Services\Integrations\SpotPlayerService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class ProvisionSpotPlayerEnrollmentJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesProvisioningStatus;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $enrollmentId) {}

    public function handle(SpotPlayerService $service, SettingsService $settings): void
    {
        $config = $settings->get(SettingKeyEnum::SPOT_PLAYER);

        if (! ($config['enabled'] ?? false)) {
            return;
        }
        if (empty($config['endpoint']) || empty($config['api_key'])) {
            throw new RuntimeException('SpotPlayer configuration is missing endpoint or api_key.');
        }
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $details = $enrollment->productDeliveryOption?->details_json ?? [];
        $spotId  = data_get($details, 'course_id');
        if (! is_string($spotId) || $spotId === '') {
            throw new RuntimeException('SpotPlayer spot_id is missing from delivery option details.');
        }

        $result = $service->issueLicense($spotId, $enrollment->customer);

        $this->markProvisioningSuccess($enrollment, 'spotplayer', [
            'spot_id'      => $spotId,
            'license_key'  => data_get($result, 'license_key'),
            'player_url'   => data_get($result, 'player_url'),
            'raw_response' => data_get($result, 'raw'),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $this->markProvisioningFailure($enrollment, 'spotplayer', $exception->getMessage());
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
