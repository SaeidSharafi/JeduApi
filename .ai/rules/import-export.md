---
paths:
  - 'app/Services/ImportExport/**'
  - 'app/Actions/Admin/ImportExport/**'
  - 'app/Http/Controllers/Api/Admin/ImportExport/**'
---

# Spreadsheet Import/Export Engine

## The uploader owns the spreadsheet shape
The downloadable template defines the expected structure: one worksheet, the first row as heading row, data below it. Do NOT add checks that detect or repair shape problems — merged cells, extra worksheets, a heading row that is not the first row, stray columns, cell formatting, or macros. Extra worksheets are ignored, the first worksheet wins, and the file either matches the column contract or it does not. Staff upload their own file and are responsible for it; the engine only resolves headings, maps cells, and validates values. YAGNI applies here on purpose.

## Engine mechanics vs resource contract
`ImportPreviewEngine` owns spreadsheet mechanics only: heading resolution through `HeadingNormalizer`, required/unknown heading errors, the 2000 data row limit, blank-row skipping, duplicate identities inside the file, and the preview envelope. Everything resource-specific — columns, rules, enum labels, Jalali conversion, identity matching, provider flags — stays in the resource contract behind `App\Contracts\ImportExport\ImportResourceContract`. Resource names resolve only through `SpreadsheetResourceRegistry`; a request value must never become a class name. Adding a resource means one contract class in `app/Services/ImportExport/Resources` plus one line in the registry.

## Stable keys are part of the contract
Routes (`/api/v1/admin/{resource}/import`, `/api/v1/admin/{resource}/import/template`), the preview envelope (`run_id`, `resource`, `status`, `identity_key`, `summary` counters, `rows[].row_number/status/operation/data/errors`, `can_approve`, `approval_warning`) and row error objects (`{field, code, message}` with `required`, `identity_conflict`, `duplicate_identity`, `invalid`) are fixed by `docs/Architecture/Admin-Import-Export-API.md` and `docs/Architecture/Admin-Import-Export-Frontend-Guide.md`. Do not rename them for convenience; the frontend branches on these exact keys. Resource values never leak to the row root — they live under `rows[].data`, and passwords never appear in a response or in `import_run_rows.data`.
