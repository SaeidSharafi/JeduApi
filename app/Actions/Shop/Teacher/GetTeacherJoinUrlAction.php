<?php

declare(strict_types=1);

namespace App\Actions\Shop\Teacher;

use App\Contracts\Integrations\NiliroomClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Data\Shop\Student\JoinUrlData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Exceptions\Integrations\ResourceNotProvisionedException;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use InvalidArgumentException;

final readonly class GetTeacherJoinUrlAction
{
    /**
     * Skyroom access level for a room presenter: enters and teaches, cannot edit the room.
     */
    private const int SKYROOM_PRESENTER_ACCESS = 2;

    private const int SKYROOM_LOGIN_URL_TTL_SECONDS = 3600;

    public function __construct(
        private SkyroomClientContract $skyroomService,
        private NiliroomClientContract $niliroomService,
    ) {}

    public function handle(User $user, ProductDeliveryOption $deliveryOption): JoinUrlData
    {
        $deliveryMethod = $deliveryOption->delivery_method;

        if (! in_array($deliveryMethod, DeliveryMethodEnum::getSeminars(), true)) {
            throw new InvalidArgumentException(__('messages.enrollments.not_seminar'));
        }

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM  => $this->buildSkyroomLoginUrl($user, $deliveryOption),
            DeliveryMethodEnum::LIVE_SESSION_NILIROOM => $this->buildNiliroomLoginGrant($user, $deliveryOption),
            // Only reachable if getSeminars() grows a method before its handler lands.
            default => throw new InvalidArgumentException(
                __('messages.enrollment.delivery_no_join_url', ['method' => $deliveryMethod->value])
            ),
        };
    }

    /**
     * No Skyroom user is created: the login URL drops the teacher into the staff-created room as a presenter.
     */
    private function buildSkyroomLoginUrl(User $user, ProductDeliveryOption $deliveryOption): JoinUrlData
    {
        $roomId = data_get($deliveryOption->details_json, 'room_id');

        // Same room-id contract as SkyroomProvisioningProvider: a positive integer, possibly stored as a numeric string.
        if (! is_int($roomId) && ! (is_string($roomId) && ctype_digit($roomId))) {
            throw new ResourceNotProvisionedException(__('messages.provisioning.skyroom_room_id_missing'));
        }

        $roomId = (int) $roomId;

        if ($roomId < 1) {
            throw new ResourceNotProvisionedException(__('messages.provisioning.skyroom_room_id_missing'));
        }

        $loginUrl = $this->skyroomService->createLoginUrl(
            roomId: $roomId,
            userId: 'user-'.$user->id,
            nickname: $this->nickname($user),
            access: self::SKYROOM_PRESENTER_ACCESS,
            ttl: self::SKYROOM_LOGIN_URL_TTL_SECONDS,
        );

        return new JoinUrlData(
            url: $loginUrl,
            type: 'skyroom',
            expires_at: verta(now()->addSeconds(self::SKYROOM_LOGIN_URL_TTL_SECONDS)), // @phpstan-ignore argument.type (verta stub types $datetime as null)
        );
    }

    /**
     * Build the teacher's Niliroom login grant for the delivery option's room.
     */
    private function buildNiliroomLoginGrant(User $user, ProductDeliveryOption $deliveryOption): JoinUrlData
    {
        $roomId = data_get($deliveryOption->details_json, 'nili_room_id');
        $roomId = is_string($roomId) ? mb_trim($roomId) : '';

        if ($roomId === '') {
            throw new ResourceNotProvisionedException(__('messages.provisioning.niliroom_room_id_missing'));
        }

        if (! $this->niliroomService->isReady()) {
            throw new ResourceNotProvisionedException(__('messages.enrollments.niliroom_not_configured'));
        }

        $grant = $this->niliroomService->issueTeacherLoginGrant($user, $roomId);

        return new JoinUrlData(
            url: $grant['url'],
            type: 'niliroom',
            expires_at: verta($grant['expires_at']), // @phpstan-ignore argument.type (verta stub types $datetime as null)
        );
    }

    private function nickname(User $user): string
    {
        return mb_trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'استاد';
    }
}
