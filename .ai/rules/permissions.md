# Permissions
Glob: `app/Enums/**`, `app/Policies/**`

- Admin endpoints: `Gate::authorize()` in the controller, using the appropriate policy method.
- Policies live in `app/Policies/Admin/`, keyed via `PermissionEnum`. Register in `app/Providers/AuthServiceProvider.php`.
- Add a new permission: edit `config/permission-generator.php`, then run `sail artisan permissions:generate-enum` followed by `sail artisan permissions:sync`.
- Never hardcode permission strings — always reference `PermissionEnum` constants.

