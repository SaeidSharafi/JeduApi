<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use App\Data\Admin\Staff\ShowStaffData;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class AdminAuditLogData extends Data
{
    public function __construct(
        public int $id,
        public ShowStaffData $admin,
        public string $action_type,
        public ?string $resource_type,
        public ?int $resource_id,
        public string $route_name,
        public string $http_method,
        public ?array $request_data,
        public int $response_status,
        public string $ip_address,
        public ?string $user_agent,
        public ?string $session_id,
        public string $risk_level,
        public ?array $metadata,
        public ?Verta $created_at,
        public mixed $resource = null,
        public string $action_summery,
    ) {}
}
