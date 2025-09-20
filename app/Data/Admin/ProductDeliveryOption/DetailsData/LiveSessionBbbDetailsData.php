<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;

final class LiveSessionBbbDetailsData extends Data implements DeliveryOptionDetailDataContract
{
    public function __construct(
        #[Nullable, StringType, Max(255)]
        public ?string $moderator_password,

        #[Nullable, StringType, Max(255)]
        public ?string $attendee_password,

        #[Nullable, BooleanType]
        public ?bool $record_session,

        #[Nullable, BooleanType]
        public ?bool $auto_start_recording,

        #[Nullable, BooleanType]
        public ?bool $allow_start_stop_recording,

        #[Nullable, BooleanType]
        public ?bool $webcams_only_for_moderator,

        #[Nullable, BooleanType]
        public ?bool $mute_on_start,

        #[Nullable, BooleanType]
        public ?bool $allow_mods_to_unmute_users,

        #[Nullable, BooleanType]
        public ?bool $lock_settings_disable_cam,

        #[Nullable, BooleanType]
        public ?bool $lock_settings_disable_mic,

        #[Nullable, BooleanType]
        public ?bool $lock_settings_disable_private_chat,

        #[Nullable, BooleanType]
        public ?bool $lock_settings_disable_public_chat,

        #[Nullable, BooleanType]
        public ?bool $lock_settings_disable_note,

        #[Nullable, BooleanType]
        public ?bool $lock_settings_locked_layout,

        #[Nullable, StringType, Max(255)]
        public ?string $welcome_message,

        #[Nullable, IntegerType] // In minutes
        public ?int $session_duration,

        #[Nullable, Url]
        public ?string $default_presentation_url,

        #[Nullable, StringType, Max(2000)]
        public ?string $admin_notes
    ) {}
}
