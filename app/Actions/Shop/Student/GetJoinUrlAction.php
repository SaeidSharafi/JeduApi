<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Data\Shop\Student\JoinUrlData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\BbbService;
use App\Services\Integrations\SkyroomService;
use InvalidArgumentException;

final readonly class GetJoinUrlAction
{
    public function __construct(
        private BbbService $bbbService,
        private SkyroomService $skyroomService,
    ) {}

    public function handle(Enrollment $enrollment): JoinUrlData
    {
        $deliveryOption = $enrollment->productDeliveryOption;
        $deliveryMethod = $deliveryOption->delivery_method;
        $provisioning   = $enrollment->provisioning_data['providers'] ?? [];

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LIVE_SESSION_BBB     => $this->buildBbbJoinUrl($enrollment, $provisioning),
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM => $this->buildSkyroomJoinUrl($enrollment, $provisioning),
            default                                  => throw new InvalidArgumentException(
                "Delivery method [{$deliveryMethod->value}] does not support join URLs."
            ),
        };
    }

    private function buildBbbJoinUrl(Enrollment $enrollment, array $provisioning): JoinUrlData
    {
        $meetingId = data_get($provisioning, 'bbb.data.meeting_id');

        if (! $meetingId) {
            throw new ExternalProvisioningException('BBB meeting not provisioned yet.');
        }

        $joinUrl = $this->bbbService->buildJoinUrl(
            meetingId: (string) $meetingId,
            fullName: $enrollment->customer->full_name ?? 'دانشجو',
        );

        return new JoinUrlData(url: $joinUrl, type: 'bbb');
    }

    private function buildSkyroomJoinUrl(Enrollment $enrollment, array $provisioning): JoinUrlData
    {
        $roomId = data_get($provisioning, 'skyroom.data.room_id');

        if (! $roomId) {
            throw new ExternalProvisioningException('Skyroom room not provisioned yet.');
        }

        $customer = $enrollment->customer;
        $joinUrl  = $this->skyroomService->createLoginUrl(
            roomId: (int) $roomId,
            userId: 'user-'.$enrollment->customer_id,
            nickname: $customer?->full_name ?? 'دانشجو',
        );

        return new JoinUrlData(url: $joinUrl, type: 'skyroom');
    }
}
