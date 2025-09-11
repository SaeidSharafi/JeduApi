<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use App\Data\Admin\Staff\StaffListItemData;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class AdminAuditLogListData extends Data
{
    public function __construct(
        public int $id,
        public StaffListItemData $admin,
        public ?string $route_name,
        public string $action_type,
        public ?string $resource_type,
        public ?int $resource_id,
        public string $http_method,
        public int $response_status,
        public string $ip_address,
        public string $risk_level,
        public ?Verta $created_at,
        public string $action_summery,
    ) {}
}
