<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;

final class LiveSessionSkyroomDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
        #[Nullable, StringType, Max(255)]
        public string $meeting_name_identifier,

        #[Nullable, StringType, Max(255)]
        public ?string $moderator_password_override, // Admin can optionally override the global moderator password for this specific meeting

        #[Nullable, StringType, Max(255)]
        public ?string $attendee_password, // Password for attendees (if this meeting specifically requires one)

        #[Nullable, BooleanType]
        public ?bool $record_session, // Admin decides if THIS session should be recorded (default might be false)

        #[Nullable, BooleanType]
        public ?bool $auto_start_recording,

        #[Nullable, BooleanType]
        public ?bool $webcams_only_for_moderator,

        #[Nullable, BooleanType]
        public ?bool $mute_on_start,

        #[Nullable, StringType, Max(1000)]
        public ?string $welcome_message, // Custom welcome message for THIS session

        #[Nullable, IntegerType] // In minutes
        public ?int $planned_duration_minutes, // Admin sets the expected duration for this specific session

        #[Nullable, Url] // URL to a specific presentation for THIS session
        public ?string $default_presentation_url,

        #[Nullable, StringType, Max(2000)]
        public ?string $admin_notes // Internal notes for this specific session setup
    ) {}
}
