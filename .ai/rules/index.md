# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
|---|---|
| `routes/Api/**` | `routing.md` |
| `app/Http/Controllers/Api/**`, `app/Data/**` | `api-docs.md` |
| `app/Data/**` request DTOs with date input | `jalali-mutation-dates.md` |
| `app/Enums/**`, `app/Policies/**` | `permissions.md` |
| `app/Models/**` | `.ai/rules/models.md` | 
No glob match → root AGENTS.md rules still apply. Add new rule files with `record-rule` rather than editing AGENTS.md directly, and update this table to match.
