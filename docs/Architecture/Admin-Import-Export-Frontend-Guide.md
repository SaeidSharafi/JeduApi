# Admin User Import/Export — Frontend Guide

This guide describes the first frontend implementation for the centralized spreadsheet system. The first release intentionally needs only a simple upload form, a validation/result view, and an explicit approval action.

## First-release workflow

```text
Download template
      ↓
Choose identity key + upload XLSX
      ↓
Show preview and validation results
      ↓
User explicitly approves valid rows
      ↓
Show local import and provider provisioning results
```

The upload step never changes users or calls external providers. The approval step is the only step that mutates data.

## Endpoints

Use the existing authenticated admin API base URL and the resource routes below.

| Purpose | Method | Endpoint |
| --- | --- | --- |
| Download import template | `GET` | `/api/v1/admin/users/import/template` |
| Export users | `GET` | `/api/v1/admin/users/export` |
| Upload and preview | `POST` | `/api/v1/admin/users/import?identity_key=phone` |
| Read import result | `GET` | `/api/v1/admin/users/import/{run}` |
| Approve valid rows | `POST` | `/api/v1/admin/users/import/{run}/approve` |
| Download errors | `GET` | `/api/v1/admin/users/import/{run}/errors` |

The available identity keys are currently `phone` and `email`.

## Minimal UI

### Upload form

Required controls:

1. Identity key select: `Phone` or `Email`.
2. XLSX file picker.
3. Upload/Validate button.
4. Download template button.

The first release does not need custom column mapping, provider selection controls, a retry dashboard, multi-step wizard navigation, or asynchronous preview polling.

The backend limit is 2,000 data rows. Display a friendly error if the server rejects a file because of size, type, worksheet, header, or row-count limits.

### Preview result

After a successful upload, display:

- import run identifier;
- selected identity key;
- total row count;
- valid row count;
- invalid row count;
- create count;
- update count;
- provider provisioning request count;
- row-level validation messages;
- a clear warning that no data has been changed yet.

The preview response must use this canonical shape. Frontend code should use these names exactly; do not invent aliases or derive replacement keys.

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
      },
      {
        "row_number": 3,
        "status": "invalid",
        "operation": null,
        "data": {
          "phone": "invalid"
        },
        "errors": [
          {
            "field": "phone",
            "code": "invalid",
            "message": "The phone number is invalid."
          }
        ]
      }
    ],
    "can_approve": true,
    "approval_warning": "No data has been changed. Approval will import all valid rows."
  }
}
```

The API may paginate `rows` later, but the field names and nesting must remain stable. `can_approve` is the backend decision; the frontend should not reproduce approval rules from counts or status. `rows[].data` is the resource-specific normalized payload; resource fields must never be added as dynamic keys at the row root.

Show an approval button only when the run is `preview_ready` and valid rows exist.

When the preview contains invalid rows, the approval action must send exactly:

```json
{
  "include_valid_rows": true
}
```

This flag explicitly confirms that the administrator accepts importing only the valid rows and ignoring the invalid rows. When every row is valid, the approval request body is empty; `include_valid_rows` is not required. Do not approve automatically after upload. The explicit approval is always required to prevent accidental bulk mutation.

### Approval and polling

Approval commits local users and queues provider provisioning. Provider jobs have not completed when the approval response arrives, so do not display final provider results from that response.

The approval response uses this shape:

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

After receiving approval, poll `GET /api/v1/admin/users/import/{run}` using the returned `run_id`. Stop polling only when the run reaches `completed`, `completed_with_provider_failures`, `failed`, or `expired`.

The polling response contains final local/provider results. A row may have a result like:

```json
{
  "local_user": "succeeded",
  "providers": {
    "moodle": "succeeded",
    "ims": "retryable_failed"
  }
}
```

Use these meanings:

| Status | Meaning |
| --- | --- |
| `preview_ready` | File validated; no mutation has happened |
| `approved` | Approval accepted; processing is starting |
| `processing` | Local/provider work is in progress |
| `completed` | Local import and requested providers succeeded |
| `completed_with_provider_failures` | Local import succeeded; one or more provider operations failed or remain retryable |
| `failed` | The local import could not complete |
| `expired` | The run or its artifacts are no longer available |

Provider failures do not mean that local users failed. Present them as a separate warning and provide the error-report link when available.

The run-status response must use this canonical shape:

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
          "moodle": {
            "status": "succeeded",
            "message": null
          }
        },
        "errors": []
      },
      {
        "row_number": 4,
        "status": "completed_with_provider_failures",
        "operation": "updated",
        "data": {
          "id": 124,
          "phone": "09121111111"
        },
        "providers": {
          "ims": {
            "status": "retryable_failed",
            "message": "Provider is temporarily unavailable."
          }
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

For `preview_ready`, use `valid_rows`, `invalid_rows`, `create_count`, and `update_count`. After approval, use `created_count`, `updated_count`, `local_failure_count`, `provider_success_count`, `provider_failure_count`, and `retryable_provider_failure_count`. Do not rename these fields to a generic `success_count`.

### Error response shape

Validation and API errors continue to use the application’s standard error envelope. For import-specific row errors, use:

```json
{
  "data": {
    "run_id": "01jexampleimportrun",
    "resource": "users",
    "errors": [
      {
        "row_number": 3,
        "field": "phone",
        "code": "identity_conflict",
        "message": "The phone number belongs to another user."
      }
    ],
    "download_url": "/api/v1/admin/users/import/01jexampleimportrun/errors"
  }
}
```

`message` is displayable text. `code` is stable for frontend behavior. `field` is the canonical spreadsheet field name. Never display or log password values.

## Heading and value expectations

The backend accepts English and Persian headings. The frontend does not need to normalize headings manually.

The template is the safest way for users to obtain correct headings. If users upload an edited file, the backend normalizes common Persian/Arabic character variants, zero-width characters, whitespace, punctuation, and diacritics.

Provider request columns are additive and appear in the template when supported:

```text
provision_moodle
provision_ims
provision_spotplayer
```

Accepted boolean values include:

```text
true:  1, true, yes, بله
false: 0, false, no, خیر
```

An absent, blank, or false provider column means “do nothing.” It never disables or deletes a provider account.

The optional `password` column may create or update a local password. Password values must never be rendered in the frontend result, error display, logs, browser state, analytics, or downloaded error artifact. Empty password cells preserve the existing password on updates.

## Validation display

Validation errors should be grouped by row. Display the spreadsheet row number and a safe, translated message. Examples:

- missing required field;
- invalid phone/email;
- unsupported identity key;
- duplicate identity in the file;
- identity conflicts with another existing user;
- invalid boolean value;
- invalid password;
- unsupported heading;
- invalid provider request.

The frontend should not try to reconstruct or fix backend validation rules. Users should download the template or correct the file based on the returned row messages.

## Export

The export action should preserve the current User list filters and sorting. Pagination must not affect export results.

The exported file contains safe user/profile fields only. Passwords, access tokens, reset tokens, provider credentials, and internal provisioning data are never exported.

Enum values are translated labels, not raw enum codes. Dates are formatted Jalali values, matching the application’s Verta display format.

If the backend returns a stored export/run reference instead of a direct file response, the frontend should show a loading state and download the completed private artifact from the provided authenticated endpoint. Do not regenerate the export automatically after a connection interruption.

## Permissions and errors

The frontend should hide or disable controls when the current staff member lacks the relevant permission, but the backend remains authoritative. Handle `401`, `403`, `404`, `409`, `422`, and `5xx` responses using existing application error conventions.

Important cases:

- `403`: staff member lacks the operation permission;
- `422`: file or row validation failed;
- `409`: run is already approved or is in a non-approvable state;
- `404`: run/artifact is missing or inaccessible;
- `5xx`: temporary server failure; do not automatically repeat approval.

## First-release acceptance checklist

- Staff can download the XLSX template.
- Staff can choose phone or email identity matching.
- Staff can upload an XLSX file and see preview counts/results.
- Upload does not mutate users or call providers.
- Staff must explicitly approve; when invalid rows exist, approval must include `include_valid_rows=true`.
- Staff can see local success/failure separately from provider success/failure.
- Invalid rows are not imported or provisioned.
- Passwords never appear in UI results or error artifacts.
- Staff can download a safe error report.
- Staff can export filtered/sorted users.
- Exported enum labels are translated and dates are Jalali/Verta formatted.
- The UI does not require a retry dashboard for the first release.

## Deferred frontend features

These are reserved for later and should not block the first implementation:

- asynchronous preview polling;
- manual retry controls for failed provider operations;
- CSV template/output controls;
- custom column mapping;
- provider disable/delete controls;
- relationship/enrollment imports;
- recurring imports;
- multi-sheet import workflows.
