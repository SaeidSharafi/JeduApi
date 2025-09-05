<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class SuspiciousActivityData extends Data
{
    public function __construct(
        public int $transaction_id,
        public int $user_id,
        public string $user_name,
        public int $amount,
        public string $type,
        public string $created_at,
        public string $hour,
        public string $flags,
        public string $admin_initiated,
        public ?string $ip_address = null,
        public ?int $transaction_count = null,
        public ?int $total_volume = null,
        public ?string $first_transaction = null,
        public ?string $last_transaction = null,
        public ?string $avg_transaction_amount = null,
        public ?string $pattern = null,
    ) {
    }
}
