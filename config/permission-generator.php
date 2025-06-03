<?php

declare(strict_types=1);

use SaeidSharafi\LaravelPermissionGenerator\Enums\PermissionAction;

// You might also import your application's custom Enums if using option #4 below
// use App\Enums\MyCustomActions;

return [

    /**
     * Output Enum Class Path.
     * (Same description as before)
     */
    'output_enum' => app_path('Enums/PermissionEnum.php'),

    'enum_class' => 'App\\Enums\\PermissionEnum', // Default Enum class

    /**
     * Define Resources and Their Permissions.
     * --------------------------------------------------------------------------
     * List your application's resources and their required permission actions.
     * Resource names are pluralized and snake_cased (e.g., 'userProfile' -> 'user_profiles.action').
     *
     * Define actions using one of the following methods:
     *
     * 1. Strings for Standard Scoped Patterns: (Simple & Recommended for Standard Patterns)
     *    - 'view_scoped'   -> Generates 'resource.view_any', 'resource.view'
     *    - 'update_scoped' -> Generates 'resource.update_any', 'resource.update'
     *    - 'delete_scoped' -> Generates 'resource.delete_any', 'resource.delete'
     *    The generator specifically recognizes these string values to apply the 'any/simple' logic.
     *
     * 2. Strings for Simple/Specific Actions: (Simple & Recommended for Custom Actions)
     *    - 'create', 'view_any', 'manage', 'publish', 'impersonate', etc.
     *    Generates the literal permission string 'resource.action'.
     *
     * 3. PermissionAction Enum Constants: (Optional Clarity for Standard Patterns)
     *    - use SaeidSharafi\LaravelPermissionGenerator\Enums\PermissionAction;
     *    - Use `PermissionAction::VIEW_SCOPED`, `PermissionAction::CREATE`, etc.
     *    This provides type-hinting. The generator uses the underlying string value
     *    (e.g., 'view_scoped') to check for standard patterns (see point #1).
     *
     * 4. Your Own Custom Backed Enum Constants: (Advanced Option)
     *    - If you have your own `BackedEnum` (string or int backed) defining actions:
     *      `use App\Enums\MyCustomActions;`
     *      `MyCustomActions::APPROVE`, `MyCustomActions::PUBLISH_ADVANCED`
     *    - The generator will extract the string/int value from your Enum case.
     *    - **Important Limitation:** The generator will *NOT* apply the special '_scoped'
     *      logic based on your custom Enum structure. It only recognizes the specific
     *      *string values* like 'view_scoped'. Your Enum primarily serves to provide
     *      the action's string value in an organized way. For example, if you have
     *      `MyActionEnum::APPROVE_SCOPED = 'approve_scoped'`, the generator will only
     *      create the single permission `resource.approve_scoped`.
     */
    'resources' => [
        'course' => [
            PermissionAction::VIEW_SCOPED,
            PermissionAction::CREATE,
            PermissionAction::UPDATE,
            PermissionAction::UPDATE_OWN,
            PermissionAction::DELETE,
            PermissionAction::DELETE_OWN,
        ],
        'category' => [
            PermissionAction::VIEW_SCOPED,
            PermissionAction::CREATE,
            PermissionAction::UPDATE,
            PermissionAction::UPDATE_OWN,
            PermissionAction::DELETE,
            PermissionAction::DELETE_OWN,
        ],
        'file' => [
            PermissionAction::VIEW_SCOPED,
            PermissionAction::CREATE,
            PermissionAction::UPDATE,
            PermissionAction::UPDATE_OWN,
            PermissionAction::DELETE,
            PermissionAction::DELETE_OWN,
        ],
        'seminar' => [
            PermissionAction::VIEW_SCOPED,
            PermissionAction::CREATE,
            PermissionAction::UPDATE,
            PermissionAction::UPDATE_OWN,
            PermissionAction::DELETE,
            PermissionAction::DELETE_OWN,
        ],
        'staff' => [
            PermissionAction::VIEW_SCOPED,
            PermissionAction::CREATE,
            PermissionAction::UPDATE,
            PermissionAction::DELETE,
            'manage_roles',
            'impersonate',
        ],
        'role' => [
            PermissionAction::VIEW_SCOPED,
            PermissionAction::CREATE,
            PermissionAction::UPDATE,
            PermissionAction::DELETE,
        ],
        'teacher' => [
            PermissionAction::VIEW_SCOPED,
            PermissionAction::CREATE,
            PermissionAction::UPDATE,
            PermissionAction::DELETE,
        ],
        /*
        'user' => [
            PermissionAction::VIEW_SCOPED,      // Standard Enum: users.view_any, users.view
            PermissionAction::CREATE,           // Standard Enum: users.create
            'update_scoped',                    // Standard String: users.update_any, users.update
            'delete_scoped',                    // Standard String: users.delete_any, users.delete
            'manage_roles',                     // Custom String:   users.manage_roles
            // MyCustomActions::IMPERSONATE,    // Custom Enum (if defined): users.impersonate (assuming value is 'impersonate')
        ],

        'post' => [
            'view_scoped',                      // Standard String: posts.view_any, posts.view
            PermissionAction::CREATE,           // Standard Enum: posts.create
            PermissionAction::UPDATE_SCOPED,    // Standard Enum: posts.update_any, posts.update
            'delete_scoped',                    // Standard String: posts.delete_any, posts.delete
            'publish',                          // Custom String:   posts.publish
            'feature',                          // Custom String:   posts.feature
        ],
        */

        /* --- Add your actual resources below --- */

    ],

    /**
     * Define Custom Permissions (Standalone).
     * (Same description and commented-out examples as before)
     */
    'custom_permissions' => [
        /* Example:
        'ACCESS_ADMIN_DASHBOARD' => 'staff.dashboard.access',
        'VIEW_AUDIT_LOGS'        => 'system.audit.view',
        'MANAGE_SETTINGS'        => 'system.settings.manage',
        */
    ],

    /**
     * Super Admin Role Name.
     * (Same description as before)
     */
    'super_admin_role' => 'super-admin',

    /**
     * Remove Stale Permissions.
     * (Same description as before)
     */
    'remove_stale_permissions' => false,

];
