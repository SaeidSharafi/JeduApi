<?php

declare(strict_types=1);

namespace App\Data\Admin\Notification;

use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;

final class StaffNotificationData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $message,
        public ?string $resource_type,
        public int|string|null $resource_id,
        public ?string $read_at,
        public ?string $created_at,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        $data = $notification->data;

        return new self(
            (string) $notification->getKey(),
            $notification->type,
            (string) ($data['title'] ?? ''),
            (string) ($data['message'] ?? ''),
            isset($data['resource_type']) ? (string) $data['resource_type'] : null,
            $data['resource_id'] ?? null,
            $notification->read_at?->toISOString(),
            $notification->created_at?->toISOString(),
        );
    }
}
