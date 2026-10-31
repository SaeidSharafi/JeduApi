<?php

declare(strict_types=1);

namespace App\Data\Shop\Notification;

use Spatie\LaravelData\Data;

final class UnreadNotificationCountData extends Data
{
    public function __construct(public int $count) {}
}
