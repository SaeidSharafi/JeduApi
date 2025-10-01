#codebase

Your task is to update my existing "Codebase Digest" files to reflect recent changes in the codebase. The digest files are located in the docs folder (#file:DIGEST_API_INTERFACES.md #file:DIGEST_CORE_LOGIC.md #file:DIGEST_DATA_MODELS.md #file:DIGEST_CORE_LOGIC.md ).

I need you to perform a `git diff` to find all the code changes between these two commits, you can use the `GitKraken` MCP server or any other git diff tool you prefer.:
- **Previous State Commit:** `62f7320f`
- **Current State Commit:** `f8a3d0ba`

Based on the diff, I need you to update these files:
- `CODEBASE_DIGEST.md`
- `DIGEST_DATA_MODELS.md`
- `DIGEST_CORE_LOGIC.md`
- `DIGEST_API_INTERFACES.md`

Please follow these precise instructions for the update:

1.  **Analyze the `git diff`:** Identify all added, modified, and deleted files in the `app/`, `routes/`, and `database/migrations/` directories.

2.  **Modify `DIGEST_DATA_MODELS.md`:**
    - If you find a new migration, **add** a new model entry or **update** the `Key Fields` on an existing entry.
    - If you find changes to Eloquent relationships in an `app/Models/` file, **modify** the `Relationships` section for that model.

3.  **Modify `DIGEST_CORE_LOGIC.md`:**
    - If you find a new Action/Service class, **add** a new entry for it, documenting its purpose and public methods.
    - If an existing Action/Service was changed, **update** its method list or summaries.
    - If a class was deleted, **remove** its entry.

4.  **Modify `DIGEST_API_INTERFACES.md`:**
    - If you find changes in the route files, **add, remove, or update** the corresponding route entries under the correct controller.
    - If a controller was changed to use a new DTO or call a different service, **update** the `Request DTO`, `Response DTO`, or `Delegates to` lines for that route.

5. **Modify `DIGEST_SCHEMA.md`:**
    - If you find changes in the database schema (new tables, modified columns, deleted tables), **update** the relevant sections to reflect these changes.

New Features

Admin can manage product relationships (related, cross-sell, upsell): list, filter by type, bulk attach/sync, and delete.
New product select-options endpoint for dropdowns with id, title, subtitle, and type; supports search, limit, and type filters.
Improvements

Refined product search matching for more accurate select-option results.
Database

Added related_products table with relation type, timestamps, indexes, and uniqueness constraints.
Tests

Comprehensive API and unit tests covering permissions, validations, syncing, filtering, and deletion.
Localization

Added validation messages for related-product errors.
