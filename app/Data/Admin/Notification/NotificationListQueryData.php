<?php

declare(strict_types=1);

namespace App\Data\Admin\Notification;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/** Query parameters for the staff notification list. */
final class NotificationListQueryData extends Data
{
    public function __construct(
        public ?array $filter = null,
        public ?int $per_page = null,
        public ?int $page = null,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'filter'   => ['sometimes', 'array'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'     => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function queryParameters(): array
    {
        return [
            'filter[unread]' => ['description' => 'Filter by unread state.', 'example' => true],
            'per_page'       => ['description' => 'Number of notifications per page.', 'example' => 15],
            'page'           => ['description' => 'Page number.', 'example' => 1],
        ];
    }
}
