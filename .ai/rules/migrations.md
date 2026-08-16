---
paths:
  - 'database/migrations/**'
---

# Migrations

## Enum columns are strings, never native enum
Never use ->enum() in migrations. Enum-backed columns use $table->string(...)->index() (or a composite index). Values are PHP backed enums using the AdvanceEnum trait. Confirmed: zero native enum columns exist; keep it that way. Applies to new columns like threshold_scope on wallet_campaigns.
