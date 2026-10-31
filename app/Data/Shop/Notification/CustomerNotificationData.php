<?php

declare(strict_types=1);

namespace App\Data\Shop\Notification;

use Hekmatinasser\Verta\Verta;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;

/**
 * One row of the customer notification feed.
 *
 * Mirrors the staff feed payload (`title`, `message`, `resource_type`,
 * `resource_id`) so both surfaces share one rendering contract, with the
 * timestamps returned as Jalali dates like every other response DTO.
 *
 * `type` is the public kind the writer stored in the payload
 * (`wallet_gift_allocated`), never the notification class: the PHP namespace
 * is internal and must not reach an API response.
 */
final class CustomerNotificationData extends Data
{
    public function __construct(
        public string $id,
        public ?string $type,
        public string $title,
        public string $message,
        public ?string $resource_type,
        public int|string|null $resource_id,
        public ?Verta $read_at,
        public ?Verta $created_at,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        $data = $notification->data;

        return new self(
            (string) $notification->getKey(),
            isset($data['type']) ? (string) $data['type'] : null,
            (string) ($data['title'] ?? ''),
            (string) ($data['message'] ?? ''),
            isset($data['resource_type']) ? (string) $data['resource_type'] : null,
            $data['resource_id'] ?? null,
            $notification->read_at    !== null ? Verta::instance($notification->read_at) : null,
            $notification->created_at !== null ? Verta::instance($notification->created_at) : null,
        );
    }
}
