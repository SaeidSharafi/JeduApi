<?php

declare(strict_types=1);

namespace App\Data\Admin\Role;

use Spatie\LaravelData\Data;

final class PermissionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    public function with(): array
    {
        $nameArray = explode('.', $this->name);
        $resource  = $nameArray[0] ?? '';
        $action    = $nameArray[1] ?? '';
        if (! $action) {
            $action   = $resource;
            $resource = 'custom_permission';

            return [
                'resource'    => __("auth.permission.resource.{$resource}"),
                'resourceKey' => $resource,
                'label'       => __("auth.permission.custom_permission.{$action}"),
            ];
        }
        $actions     = __('auth.permission.action');
        $actionLabel = __("auth.permission.action.{$action}");
        if (is_array($actions) && ! array_key_exists($action, $actions)) {
            $actionLabel = __("auth.permission.custom.$resource.$action");
        }

        return [
            'resource'    => __("auth.permission.resource.{$resource}"),
            'resourceKey' => $resource,
            'label'       => $actionLabel,
        ];
    }
}
