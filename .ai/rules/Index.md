# Rule Index

Maps file globs to rule files below. Before editing any file, check if its path matches a glob here — if so, read that file first. It is load-bearing, not background reading.

| Glob | File | Covers |
|---|---|---|
| `routes/Api/**` | `routing.md` | Route-file-to-guard mapping |
| `app/Http/Controllers/Api/**`, `app/Data/**` | `api-docs.md` | Scribe body/query param detection, @responseFile, manual bodyParameters()/queryParameters() cases |
| `app/Enums/**`, `app/Policies/**` | `permissions.md` | Permission key generation, authorization pattern |

No glob match → root AGENTS.md rules still apply. Add new rule files with `record-rule` rather than editing AGENTS.md directly, and update this table to match.
