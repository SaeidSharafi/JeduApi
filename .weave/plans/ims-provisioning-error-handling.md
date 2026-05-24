# IMS Provisioning Error Handling & Observability

## TL;DR
> **Summary**: Enrich `ExternalProvisioningException` with structured IMS error metadata, surface it in `provisioning_data` on the `Enrollment`, emit structured log context for developers, and write an `AdminActionLog` entry so admins can diagnose failures without reading raw logs.
> **Estimated Effort**: Short

---

## Context

### Original Request
Better error handling and observability for failed IMS provisioning. IMS is an external Laravel app that returns 422 validation errors (per-field `errors` map) and generic HTTP errors. Need an organized way for both admins and developers to understand what went wrong.

### Key Findings

**`ImsService`** (`app/Services/Integrations/ImsService.php`):
- Has two separate methods: `storeSetudent` (typo, keep as-is) and `storeEnrolment`.
- Both already catch 422 and flatten `errors` into a string message, then throw `ExternalProvisioningException`.
- Non-422 failures throw with a generic string. `storeEnrolment` has a stray `$response->dd()` on line 102 — must be removed.
- Neither method preserves: HTTP status code, endpoint URL, raw response body snippet, or structured per-field errors.

**`ExternalProvisioningException`** (`app/Exceptions/Integrations/ExternalProvisioningException.php`):
- Already has a `$metaData` array property. Currently only used for Moodle `errorcode`. Can be extended for IMS structured data.

**`HandlesProvisioningStatus`** (`app/Jobs/Provisioning/Concerns/HandlesProvisioningStatus.php`):
- `markProvisioningFailure` stores only `status`, `failed_at`, `last_error` (plain string) in `provisioning_data['providers']['ims']`.
- Needs a `$metadata` parameter to store structured error context alongside the string message.

**`ProvisionImsEnrollmentJob`** (`app/Jobs/Provisioning/ProvisionImsEnrollmentJob.php`):
- `failed()` callback calls `markProvisioningFailure($enrollment, 'ims', $exception->getMessage())` — loses all structured context.
- No logging anywhere in the job.

**`AdminActionLog`** (`app/Models/AdminActionLog.php`):
- Supports `metadata` (JSON), `resource_type`/`resource_id` (morph), `action_type`, `response_status`, `risk_level`. Perfect fit for system-generated provisioning failure events. `admin_id` is nullable in the model (no FK constraint visible), so system events can use `null`.

**IMS Response Formats** (from `docs/ReponseExamples/Ims/`):
- 422: standard Laravel validation response — `{ errors: { field: ['msg', ...] } }`.
- 200 success: `{ message, data: { enrollment_id, created_at } }` or `{ message, data: { student_id, user_id } }`.
- Non-422 failures: arbitrary HTTP error body.

**Existing tests**:
- `ImsServiceTest` references `provisionEnrollment()` — a method that does not exist in the current `ImsService`. Tests are misaligned with implementation. Must be reconciled.
- `ProvisionImsEnrollmentJobTest` has `markProvisioningFailure` assertion checking `last_error` string — will need updating for new metadata shape.

---

## Objectives

### Core Objective
When IMS provisioning fails, capture structured error context (HTTP status, endpoint, per-field validation errors, raw body snippet) and surface it in two places: (1) `enrollment.provisioning_data` for admin UI, (2) `AdminActionLog` for audit trail + structured log entry for developer observability.

### Deliverables
- [ ] Migration: make `admin_action_logs.admin_id` nullable (system events have no admin)
- [ ] `ExternalProvisioningException` carries structured IMS error context (with PII sanitization)
- [ ] `ImsService` populates exception with metadata on both 422 and generic failures; `$response->dd()` removed; PII sanitization on response body; endpoint path template (no civil_id)
- [ ] `HandlesProvisioningStatus::markProvisioningFailure` accepts + stores structured metadata
- [ ] `ProvisionImsEnrollmentJob::failed` passes structured metadata from exception; adds `Log::error` with context (without raw `getMessage()`); writes `AdminActionLog` with placeholder values for HTTP-only columns
- [ ] All existing tests updated; new assertions added for metadata shape, log output, PII sanitization, and AdminActionLog

### Definition of Done
- [x] `vendor/bin/sail artisan migrate` runs cleanly (admin_id nullable migration)
- [x] `vendor/bin/sail artisan test --compact --filter=ImsService` passes
- [x] `vendor/bin/sail artisan test --compact --filter=ProvisionImsEnrollment` passes
- [x] `vendor/bin/sail bin pint --dirty --format agent` reports no issues

### Guardrails (Must NOT)
- Do NOT store PII (names, phone, email, civil_id) in `AdminActionLog.metadata` or `provisioning_data` error context
- Do NOT store the IMS `api_key` anywhere in logs or DB
- Do NOT change the `provisioning_data` success shape — only the failure shape gains new fields
- Do NOT add `auth` middleware to routes or use `actingAs()` in tests
- Do NOT use `Form Requests` or `API Resources` — no new API endpoints in this plan
- Do NOT refactor `storeSetudent` typo (out of scope, risk of breaking callers)
- Do NOT store raw IMS response text (`raw_body_snippet`, `error_message`) without PII sanitization (regex-strip email, phone, civil_id patterns)
- Do NOT propagate user-submitted values from IMS validation messages into `AdminActionLog` (use static generic message instead of flattened error string in `error_message`)

### Advanced Considerations (Post-Review Resolution)
The following were identified during review (Weft + Warp) and are incorporated into the plan below:

1. **`AdminActionLog` NOT NULL constraints**: `admin_id`, `ip_address`, `route_name`, `http_method` are all `NOT NULL`. In queue `failed()` context, there's no HTTP request or admin user. **Resolution**: Add migration to make `admin_id` nullable (semantically correct — system background events). Use placeholder values for the rest: `'route_name' => 'system:ims_provisioning'`, `'http_method' => 'QUEUE'`, `'ip_address' => '127.0.0.1'`.

2. **`endpoint` metadata leaks PII**: `storeEnrolment` URL includes `$user->civil_id`. **Resolution**: Pass **path template** (`/api/v2/enrolment/{civil_id}`) instead of actual resolved URL to `buildException()`.

3. **No PII sanitization on response body**: IMS validation errors may echo user-submitted values. **Resolution**: Add PII-strip regex (email, phone, civil_id patterns) before storing `raw_body_snippet` or `validation_errors` values.

4. **`getMessage()` propagation**: The flattened error string from IMS (containing field + message pairs) may include PII. **Resolution**: In `AdminActionLog.metadata.error_message`, use a static generic message like `"IMS validation failed"` instead of `$exception->getMessage()`. The structured `validation_errors` array (sanitized) provides the detail.

---

## TODOs

- [x] 1. Remove stray `$response->dd()` from `ImsService::storeEnrolment`
  **What**: Line 102 of `ImsService` has `$response->dd()` before the `throw`. Remove it — it would halt execution in production.
  **Files**: `app/Services/Integrations/ImsService.php`
  **Acceptance**: Line removed; `storeEnrolment` throws `ExternalProvisioningException` cleanly on failure.

- [x] 2. Enrich `ExternalProvisioningException` with IMS-specific structured context
  **What**: Add a private `buildException(Response $response, string $endpoint): ExternalProvisioningException` method on `ImsService`. The exception's `$metaData` should contain:
  ```php
  [
      'http_status'       => $response->status(),         // int, e.g. 422
      'endpoint'          => $endpoint,                   // string, PATH TEMPLATE only, no PII
      'validation_errors' => [...],                       // array<string, string[]> — per-field, 422 only; empty otherwise
      'raw_body_snippet'  => $this->sanitizeBody($response->body()), // ≤500 chars, PII-stripped
  ]
  ```
  **CRITICAL — PII safety**: 
  - `$endpoint` MUST be a path template (e.g., `/api/v2/enrolment/{civil_id}`) — NOT the resolved URL containing actual civil_id. Pass the template string directly, not the `->post()` argument.
  - `raw_body_snippet` MUST be sanitized via a new `sanitizeBody()` helper that regex-strips email patterns, phone numbers (09\d{9}), and civil_id (10-digit) before truncation.
  - `validation_errors` values (the string messages) MUST also be sanitized through the same helper.
  - `validation_errors` keys (field names) are safe — they're always field names, not values.
  **Files**: `app/Services/Integrations/ImsService.php`, `app/Exceptions/Integrations/ExternalProvisioningException.php`
  **Acceptance**: Throwing `ExternalProvisioningException` from either IMS method populates `metaData` with the four keys above; no PII survives sanitization.

- [x] 3. Update `ImsService` error handling to use enriched exception
  **What**: 
  - Replace the current 422 string-flattening logic in both `storeSetudent` and `storeEnrolment` with a shared private method `buildException(Response $response, string $endpoint): ExternalProvisioningException`.
  - `buildException()` does the following:
    - Extracts `errors` array from response JSON for 422; flattens to string for exception `message` (existing behavior preserved).
    - Populates `metaData` as defined in TODO 2, with **sanitized** values.
    - For non-422 failures, sets `validation_errors => []` and uses generic message.
  - Add `sanitizeBody(string $body): string` private method:
    ```php
    private function sanitizeBody(string $body): string
    {
        $sanitized = preg_replace(
            ['/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/', '/\b09\d{9}\b/', '/\b\d{10}\b/'],
            '[REDACTED]',
            $body
        );
        return substr($sanitized ?? $body, 0, 500);
    }
    ```
  - `storeEnrolment` passes path template `/api/v2/enrolment/{civil_id}` to `buildException()` instead of the resolved URL.
  **Files**: `app/Services/Integrations/ImsService.php`
  **Acceptance**: Both methods throw with populated `metaData`; existing message strings unchanged (backward compat with existing test assertions); no PII in stored metadata.

- [x] 4. Update `HandlesProvisioningStatus::markProvisioningFailure` to accept structured metadata
  **What**: Add optional `array $metadata = []` parameter. Merge it into the stored provider failure record:
  ```php
  $providersData[$provider] = [
      'status'     => 'failed',
      'failed_at'  => now()->toDateTimeString(),
      'last_error' => $error,
      'metadata'   => $metadata,   // NEW: http_status, endpoint, validation_errors, raw_body_snippet
  ];
  ```
  **Files**: `app/Jobs/Provisioning/Concerns/HandlesProvisioningStatus.php`
  **Acceptance**: `provisioning_data.providers.ims.metadata` is populated when metadata passed; existing callers with no metadata arg still work (default `[]`).

- [x] 4a. Add migration making `admin_id` nullable on `admin_action_logs`
  **What**: `admin_id` is `NOT NULL` with FK to `staff(id)`. Queue `failed()` callbacks have no HTTP request or authenticated admin. Add migration to make `admin_id` nullable.
  ```php
  Schema::table('admin_action_logs', function (Blueprint $table): void {
      $table->dropForeign('admin_action_logs_admin_id_foreign');
      $table->bigInteger('admin_id')->nullable()->change();
      $table->foreign('admin_id')->references('id')->on('staff')->nullOnDelete();
  });
  ```
  **Files**: `database/migrations/yyyy_mm_dd_hhmmss_make_admin_id_nullable_in_admin_action_logs.php`
  **Acceptance**: `sail artisan migrate` runs cleanly; `admin_action_logs.admin_id` accepts null.

- [x] 5. Update `ProvisionImsEnrollmentJob::failed` to pass metadata + emit structured log
  **What**:
  - Extract `$metaData` from exception if it is `ExternalProvisioningException`, else `[]`.
  - Pass metadata to `markProvisioningFailure`.
  - Add `Log::error('IMS provisioning failed', [...])` with safe context — DO NOT include raw exception message (may contain PII from IMS validation):
    ```php
    [
        'enrollment_id' => $this->enrollmentId,
        'payment_id'    => $this->paymentId,
        'http_status'   => $metaData['http_status'] ?? null,
        'endpoint'      => $metaData['endpoint'] ?? null,
        'validation_errors' => $metaData['validation_errors'] ?? [],
        'exception_class'   => get_class($exception),
        'job_attempts'  => $this->attempts(),
    ]
    ```
  - Write `AdminActionLog` entry (system event, NOT an admin HTTP request):
    ```php
    AdminActionLog::create([
        'admin_id'        => null,          // now nullable via migration
        'action_type'     => 'ims_provisioning_failed',
        'resource_type'   => Enrollment::class,
        'resource_id'     => $enrollment->id,
        'route_name'      => 'system:ims_provisioning',  // placeholder — no HTTP route in queue
        'http_method'     => 'QUEUE',                     // placeholder — no HTTP method
        'response_status' => $metaData['http_status'] ?? 0,
        'ip_address'      => '127.0.0.1',                 // placeholder — no request IP
        'risk_level'      => 'high',
        'metadata'        => [
            'endpoint'          => $metaData['endpoint'] ?? null,
            'validation_errors' => $metaData['validation_errors'] ?? [],
            'error_message'     => 'IMS validation failed',  // static generic — raw message may contain PII
            'raw_body_snippet'  => $metaData['raw_body_snippet'] ?? null,
            'job_attempts'      => $this->attempts(),
        ],
    ]);
    ```
  **Files**: `app/Jobs/Provisioning/ProvisionImsEnrollmentJob.php`
  **Acceptance**: `failed()` writes log entry + `AdminActionLog` row + calls `markProvisioningFailure` with metadata; no PII in AdminActionLog.

- [x] 6. Update `ImsServiceTest` to align with actual `ImsService` API + PII sanitization
  **What**: Current tests call `provisionEnrollment()` which does not exist. Tests must be rewritten to call `storeSetudent()` and `storeEnrolment()` (the real methods). Add new assertions:
  - 422 response → exception `metaData['http_status']` is `422`
  - 422 response → exception `metaData['validation_errors']` is populated per-field
  - 422 response → exception `metaData['endpoint']` is a path template (no civil_id), e.g. `/api/v2/enrolment/{civil_id}`
  - 500 response → exception `metaData['http_status']` is `500`, `validation_errors` is `[]`
  - `metaData['raw_body_snippet']` is a string ≤ 500 chars
  - **PII sanitization**: When response body contains email (`test@example.com`), phone (`09123456789`), or civil_id (`1234567890`), verify `raw_body_snippet` has `[REDACTED]` instead
  - `metaData['validation_errors']` values also sanitized for PII patterns
  **Files**: `tests/Integration/Services/Integrations/ImsServiceTest.php`
  **Acceptance**: All ImsService tests pass; no reference to non-existent `provisionEnrollment`; PII sanitization verified.

- [x] 7. Update `ProvisionImsEnrollmentJobTest` for new failure shape + AdminActionLog
  **What**: The existing `marks provisioning failure on failed callback` test asserts `last_error` string — still valid. Add new assertions:
  - `provisioning_data.providers.ims.metadata.http_status` is populated when exception carries metadata
  - `provisioning_data.providers.ims.metadata.validation_errors` is array
  - `AdminActionLog` row exists with `action_type = 'ims_provisioning_failed'`, `resource_id = $enrollment->id`, `admin_id = null`, `route_name = 'system:ims_provisioning'`, `http_method = 'QUEUE'`, `ip_address = '127.0.0.1'`
  - `AdminActionLog.metadata.error_message` is static generic (`'IMS validation failed'`), NOT raw exception message (PII safety)
  - `AdminActionLog.metadata.raw_body_snippet` is `null` when exception has no metadata (e.g., plain RuntimeException)
  - `Log::error` called with `enrollment_id` in context (use `Log::spy()`)
  - When exception is plain `RuntimeException` (no metadata), `metadata` key is `[]` and `AdminActionLog` still written with placeholders
  - **PII sanitization**: When `ExternalProvisioningException` has `raw_body_snippet` containing PII, verify AdminActionLog stores redacted version
  **Files**: `tests/Integration/Jobs/Provisioning/ProvisionImsEnrollmentJobTest.php`
  **Acceptance**: All job tests pass including new metadata + log + AdminActionLog + PII safety assertions.

---

## Verification

- [x] `vendor/bin/sail artisan migrate` runs cleanly
- [x] `vendor/bin/sail artisan test --compact --filter=ImsService` — all ImsService tests green
- [x] `vendor/bin/sail artisan test --compact --filter=ProvisionImsEnrollment` — all job tests green
- [x] `vendor/bin/sail bin pint --dirty --format agent` — no formatting issues
- [x] Grep `$response->dd()` in `ImsService.php` returns no matches
- [x] Grep `AdminActionLog` metadata for PII patterns (email, phone, civil_id) in new code — no matches
- [x] Verify `admin_action_logs.admin_id` accepts null (`sail artisan tinker --execute="DB::select('SELECT is_nullable FROM information_schema.columns WHERE table_name=\'admin_action_logs\' AND column_name=\'admin_id\'')[0]->is_nullable === 'YES'"`)
