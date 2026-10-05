# Administrative Import/Export API

Planned contract for the centralized spreadsheet engine. The first implementation supports the `users` resource and an XLSX workflow. The backend is the source of truth for headings, validation, translations, provider capabilities, and templates; the frontend only needs an upload form, a result view, and an explicit approval action.

## Package

Use `maatwebsite/excel` (current 4.x line when installed). It provides Laravel-native XLSX/CSV readers and writers, heading-row support, stored and queued exports, query-based chunking, import transactions, and test helpers. The application layer remains responsible for domain mapping, Persian/English heading aliases, Unicode normalization, translated enum labels, Verta date formatting, and validation.

## Resource routes

All routes are under the existing versioned admin API and use the resource name from a fixed server-side registry. The route value must never become a class name.

```text
GET  /api/v1/admin/users/export
GET  /api/v1/admin/users/import/template
POST /api/v1/admin/users/import?identity_key=phone
GET  /api/v1/admin/users/import/{run}
POST /api/v1/admin/users/import/{run}/approve
GET  /api/v1/admin/users/import/{run}/errors
```

Use separate permissions for export, template download, import preview, import approval, and run-result access.

## Export

`GET /users/export` accepts the same `filter[...]` and `sort` parameters as the User list endpoint. Pagination is ignored. The shared User query definition supplies allowed filters, sorting, eager-loading requirements, and a stable primary-key tie-breaker.

The export is an explicit safe field allowlist. It includes user identity/profile fields and formatted timestamps, but excludes passwords, reset tokens, access tokens, provider credentials, and raw provisioning internals. Enum values are translated for the requested locale. Dates are formatted as Jalali values through `Verta`; raw database date strings are not emitted.

The preferred implementation stores the generated XLSX privately and returns a run/download reference, allowing a client to recover from a connection interruption without regenerating the file. Direct streaming is acceptable only for a bounded synchronous implementation.

## Template

`GET /users/import/template` returns an XLSX generated from `UserImport`'s column contract. It contains one header row, an example row, and documentation/notes for required fields, identity selection, accepted booleans, password behavior, and provider columns. Enum examples follow the application language. User enum cells accept Persian labels, English labels, or raw enum keys regardless of that language; Arabic/Persian letter variants and whitespace are normalized. For example, `مرد`, `Male`, and `male` all map to the same gender value.

Initial provider request columns are additive:

```text
provision_moodle
provision_ims
provision_niliroom
provision_skyroom
```

An absent, blank, or false column means no provider operation. True means ensure that provider account exists. Import never disables or deletes provider accounts and never enrolls users. Only services with a standalone account capability appear in the template; SpotPlayer is excluded. Moodle/Skyroom reconcile existing identities and Niliroom upserts its stable identity. Ambiguous IMS creates require manual verification, because the client has no safe reconciliation operation.

## Preview

`POST /users/import` accepts one XLSX file and an explicit `identity_key` of `phone` or `email`. The initial limit is 2,000 data rows. Validation is synchronous and creates an immutable Import Run without mutating users or calling providers.

Preview validation includes:

- file type, worksheet, header row, and row-count checks;
- normalized English/Persian heading aliases;
- Persian/Arabic `ی` and `ک`, whitespace, zero-width characters, punctuation, and diacritic normalization;
- required and optional field validation;
- existing-user matching using only the selected identity key;
- duplicate identities within the file;
- conflicts with another existing user's identity;
- password validation using the normal account password rules;
- explicit boolean parsing: `1`, `true`, `yes`, `بله` and `0`, `false`, `no`, `خیر` after normalization;
- provider capability and request validation.

Empty optional update cells preserve existing values. A future explicit clear marker may be added if required.

The response contains a `run_id`, status, selected identity key, counts, row-level valid/invalid results, and requested provider operations. Raw passwords are not returned or retained in the normalized preview snapshot.

The preview response uses this stable envelope and key set:

```json
{
  "data": {
    "run_id": "01jexampleimportrun",
    "resource": "users",
    "status": "preview_ready",
    "identity_key": "phone",
    "summary": {
      "total_rows": 100,
      "valid_rows": 96,
      "invalid_rows": 4,
      "create_count": 70,
      "update_count": 26,
      "provider_provisioning_request_count": 84
    },
    "rows": [
      {
        "row_number": 2,
        "status": "valid",
        "operation": "create",
        "data": {
          "phone": "09120000000",
          "provision_moodle": true
        },
        "errors": []
      }
    ],
    "can_approve": true,
    "approval_warning": "No data has been changed. Approval will import all valid rows."
  }
}
```

The backend owns `can_approve`; clients must not reproduce approval rules. Row fields are `row_number`, `status`, `operation`, `data`, and `errors`. `data` is the resource-specific normalized payload declared by the selected import contract; the engine does not expose resource fields such as `user_id` at the row root. Password values must never appear in any response.

## Approval

`POST /users/import/{run}/approve` requires an explicit approval request. When the preview contains invalid rows, the request must include:

```json
{
  "include_valid_rows": true
}
```

This flag means that the administrator accepts importing valid rows while ignoring invalid rows. When every row is valid, the approval request body is empty and `include_valid_rows` is not required. Approval remains a separate request in both cases.

Approval is idempotent. A run can be approved once; repeated approval returns the original result and never repeats local mutation.

The approval action:

1. rechecks the immutable normalized snapshot and run state;
2. creates or updates all valid local users in one database transaction;
3. commits the local transaction;
4. dispatches independent provider provisioning operations only for committed rows whose provider flag is true.

Private import files and row snapshots default to 24 hours (`IMPORT_EXPORT_RETENTION_HOURS`). Each preview stores its absolute deadline. An unapproved run expires at that deadline and approval returns the normal 422 validation envelope with an expiry message, even if its upload still exists. `can_approve` becomes false at the deadline. Approval checks expiry while holding the run lock. An already-approved retry remains idempotent after expiry. When provider work finishes, the run receives a new configured retention window; active `processing` runs are excluded from cleanup.

Invalid rows are never mutated or sent to providers. If local persistence fails, the transaction rolls back and no provider jobs are dispatched.

The approval response does not contain final provider outcomes because provider jobs have only been queued at that point. It returns the local commit result and initial queued state (`completed` immediately when no provider operations were requested):

```json
{
  "data": {
    "run_id": "01jexampleimportrun",
    "resource": "users",
    "status": "processing",
    "identity_key": "phone",
    "summary": {
      "created_count": 70,
      "updated_count": 26,
      "provider_queued_count": 84
    }
  }
}
```

The frontend polls the run-status endpoint after approval. Run-status responses use the following stable keys and may contain final provider outcomes:

```json
{
  "data": {
    "run_id": "01jexampleimportrun",
    "resource": "users",
    "status": "completed_with_provider_failures",
    "identity_key": "phone",
    "summary": {
      "total_rows": 100,
      "valid_rows": 96,
      "invalid_rows": 4,
      "created_count": 70,
      "updated_count": 26,
      "local_failure_count": 0,
      "provider_success_count": 83,
      "provider_failure_count": 1,
      "retryable_provider_failure_count": 1
    },
    "rows": [
      {
        "row_number": 2,
        "status": "completed",
        "operation": "created",
        "data": {
          "id": 123,
          "phone": "09120000000"
        },
        "providers": {
          "moodle": {"status": "succeeded", "message": null}
        },
        "errors": []
      }
    ],
    "error_report": {
      "available": true,
      "download_url": "/api/v1/admin/users/import/01jexampleimportrun/errors"
    }
  }
}
```

Preview and completion counters intentionally use different names: preview uses `create_count`/`update_count`; post-approval results use `created_count`/`updated_count`. Provider outcomes are nested by provider and use `status` plus a safe display `message`.

## Provider results and retries

Provider status is independent per row/provider. The approval response reports queued state only. A later run-status response can contain a result like:

```json
{
  "local_user": "succeeded",
  "providers": {
    "moodle": "succeeded",
    "ims": "retryable_failed"
  }
}
```

Safe transient provider failures receive at most three attempts through a separate job, with 60- and 180-second backoff. Unsafe or ambiguous IMS student creation failures are terminal and require manual verification. Retries target only failed provider operations and use per-user/provider idempotency keys. A future manual retry endpoint may retry failed provider operations from the same Import Run; re-uploading a file is not the normal retry mechanism.

## Run statuses

```text
preview_ready
processing
completed
completed_with_provider_failures
```

`processing` means local users have been committed and provider jobs are queued or running. `completed_with_provider_failures` means the local import completed but one or more provider operations remain unsuccessful.

Import-specific row errors use stable `row_number`, `field`, `code`, and `message` keys. `code` is for frontend behavior; `message` is displayable text. Standard application error envelopes remain authoritative for request-level errors.

## Error reports and retention

`GET /users/import/{run}/errors` downloads a private XLSX attachment with `row_number`, `field`, `code`, `message`, and `provider` columns. It requires staff authentication and `imports.results`; the run lookup is scoped by both registered resource and UUID. Responses use `Cache-Control: private, no-store`. Validation errors are available immediately; final failed provider outcomes are included once the run leaves `processing`. The report is built only from persisted outcomes and contains value-free localized messages, never cell values, identities, credentials, raw exceptions, or provider payloads. Polling fills the existing `error_report` object only while the report is usable. Report regeneration inherits the run deadline. At expiry, polling immediately suppresses row data/errors and report metadata; direct report access returns 404. Cleanup retains row shells and summary/provider outcome counts, clears personal values and messages, and deletes private files. `IMPORT_EXPORT_RETENTION_HOURS` defaults to 24 hours; changing it does not change existing deadlines. Private exports use persisted inventory and the same lifetime; downloads require both an unexpired signed URL and an unexpired inventory record.

`imports:cleanup-artifacts` runs hourly with scheduler overlap protection and processes bounded batches (default 100, maximum 500). It locks each run while deleting artifacts and redacting snapshots, skips active provider runs, retries failed deletions, and continues after individual failures. Successful run cleanup records `artifacts_redacted_at`, removing it from future batches. Failed cleanup records a retry deadline so untouched expired artifacts are processed before another attempt. Exports are cleaned from the persisted artifact inventory created with each export.

## Deferred features

The following are intentionally outside the first implementation but are reserved by this contract:

- queued/asynchronous preview generation;
- manual retry UI and a manual retry endpoint;
- CSV templates and output;
- explicit clear-cell markers;
- provider disablement or deletion;
- enrollment and relationship imports;
- arbitrary provider-specific spreadsheet fields;
- recurring/scheduled imports;
- all-or-nothing distributed provider transactions.
