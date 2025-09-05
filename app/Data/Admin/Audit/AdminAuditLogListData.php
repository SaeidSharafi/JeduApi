<?php

declare(strict_types=1);

namespace App\Data\Admin\Audit;

use App\Data\Admin\Staff\StaffListItemData;
use App\Models\AdminActionLog;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class AdminAuditLogListData extends Data
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
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $created_at = null,
        public string $action_summery,
    ) {}
}
