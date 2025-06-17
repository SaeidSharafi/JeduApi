<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed'   => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'permission' => [
        'resource' => [
            'course'            => 'Courses',
            'seminar'           => 'Seminars',
            'digital_asset'     => 'Files',
            'user'              => 'Users',
            'role'              => 'Roles',
            'media'             => 'Media',
            'file'              => 'Private Files',
            'category'          => 'Categories',
            'staff'             => 'Staff',
            'custom_permission' => 'Custom Permissions',
        ],
        'action' => [
            'view_any'     => 'View Any',
            'view'         => 'View',
            'view_scoped'  => 'View Scoped',
            'view_own'     => 'View Own',
            'create'       => 'Create',
            'update'       => 'Update',
            'update_own'   => 'Update Own',
            'delete'       => 'Delete',
            'delete_own'   => 'Delete Own',
            'restore'      => 'Restore',
            'force_delete' => 'Force Delete',
        ],
        'custom' => [
            'staff' => [
                'manage_roles' => 'Manage Roles',
                'impersonate'  => 'Impersonate User',
            ],
        ],
        'custom_permission' => [ // Assuming 'custom_permission' from PermissionData.php needs a general label
            'access_admin_panel' => 'Access Admin Panel', // Example, adjust as needed
        ],
    ],

];
