## Problem Statement

Administrative users need a reliable way to import and export user data in spreadsheet form. Today there is no centralized import/export capability, so each resource would otherwise need to duplicate file parsing, headings, validation, filtering, storage, permissions, and error handling.

The User resource also needs an import-only ability to request account provisioning on external learning providers. A spreadsheet operation must not create unsafe partial local data, must not accidentally call providers during validation, and must remain usable with a minimal frontend consisting of an upload form and result/approval display.

## Solution

Build a centralized spreadsheet engine with a fixed server-side resource registry and resource-specific import/export contracts. Implement User as the first vertical slice.

The engine will use `maatwebsite/excel` current 4.x line for XLSX/CSV mechanics. User-specific contracts will define safe columns, localized headings, Unicode normalization, validation, translated enum output, Jalali/Verta date formatting, identity matching, password handling, and provider request capabilities.

The import workflow is a two-step preview/approval flow. Upload and validation are synchronous for a maximum of 2,000 data rows and create an immutable Import Run without mutation. Approval is always explicit; when invalid rows exist, the request requires `include_valid_rows=true` to confirm that valid rows may be imported while invalid rows are ignored. When every row is valid, the approval body is empty. Approval commits all valid local users atomically and only then queues independent provider provisioning operations. The approval response reports local results and queued provider work; the frontend polls the Import Run status endpoint for final provider outcomes. Provider failures are eventually consistent, separately retryable, and never roll back committed local users.

## User Stories

1. As an administrator, I want to export users to XLSX, so that I can analyze or archive user data outside the application.
2. As an administrator, I want export filters to match the User list filters, so that the spreadsheet contains exactly the users I selected.
3. As an administrator, I want export sorting to match the User list sorting, so that the spreadsheet order is predictable.
4. As an administrator, I want exports to use safe explicit columns, so that passwords, tokens, credentials, and internal provisioning data cannot leak.
5. As an administrator, I want enum values translated into human-readable labels, so that exported values are understandable to staff.
6. As an administrator, I want dates formatted as Jalali values through Verta, so that exported dates match the rest of the Persian-facing application.
7. As an administrator, I want to download an official User import template, so that I know which columns and values the system accepts.
8. As an administrator, I want the template to contain an example row and field guidance, so that I can prepare a valid file without separate documentation.
9. As an administrator, I want to upload an XLSX file with English or Persian headings, so that staff can work in their preferred language.
10. As an administrator, I want Arabic/Persian character variants and invisible formatting characters normalized, so that equivalent headings are not rejected unexpectedly.
11. As an administrator, I want to choose `phone` or `email` as the import identity key, so that I control how rows match existing users.
12. As an administrator, I want a preview before any mutation occurs, so that I can see which rows are valid and invalid before approving the import.
13. As an administrator, I want duplicate identities within a file rejected, so that row order cannot silently determine the final data.
14. As an administrator, I want identity conflicts with another existing user rejected, so that imports cannot merge or overwrite the wrong account.
15. As an administrator, I want valid rows and invalid rows reported separately, so that I can understand the effect of an import before approval.
16. As an administrator, I want approval to require an explicit confirmation when invalid rows exist, so that importing valid rows while ignoring invalid rows cannot happen accidentally.
17. As an administrator, I want all valid local users committed atomically, so that a local persistence failure does not leave a partially applied import.
18. As an administrator, I want empty optional update cells to preserve existing values, so that spreadsheet omissions do not erase user data.
19. As an administrator, I want to optionally import a password, so that users can access exam workflows without consuming thousands of OTP messages.
20. As an administrator, I want imported passwords validated using normal account password rules, so that spreadsheet-created accounts have the same security standard as ordinary accounts.
21. As an administrator, I want passwords excluded from previews, exports, audit records, and error reports, so that the import process does not expose credentials.
22. As an administrator, I want to request provider provisioning per row, so that I can choose which users receive accounts on which providers.
23. As an administrator, I want provider columns to be additive and creation-only, so that an absent or false flag never disables or deletes an external account.
24. As an administrator, I want provider provisioning to run only after local approval and commit, so that invalid or unapproved rows never call external systems.
25. As an administrator, I want each provider outcome reported independently, so that a Moodle failure does not hide a successful IMS operation.
26. As an administrator, I want transient provider failures retried automatically, so that temporary external outages do not require manual intervention.
27. As an administrator, I want provider retry operations to be idempotent, so that retries do not create duplicate external accounts.
28. As an administrator, I want an import run status and result endpoint, so that a simple frontend can display progress and outcomes.
29. As an administrator, I want a private error report, so that I can correct invalid rows without exposing sensitive data.
30. As an administrator, I want generated files stored privately and expired, so that administrative data is not permanently exposed.
31. As an administrator, I want separate permissions for export, template download, preview, approval, and run results, so that access to user data and bulk mutation is controlled independently.
32. As an administrator, I want import and export actions audited, so that bulk access and mutation remain traceable.
33. As a frontend developer, I want a stable preview/approval API, so that I can implement the feature with only upload, result display, and one explicit approval action.
34. As a future resource owner, I want to register another resource through the same contracts, so that each new import/export does not recreate the engine.
35. As a future provider integration owner, I want import contracts to declare supported provider capabilities, so that provider-specific support can grow without provider conditionals in the central engine.

## Implementation Decisions

- Create a central import/export engine backed by a fixed server-side registry. Resource names resolve only to registered handlers; user input must never dynamically resolve arbitrary class names.
- Build User-specific import, export, query, column, and mapping contracts on top of shared interfaces.
- Keep the engine response resource-agnostic. Row lifecycle metadata uses stable engine keys; all selected-resource values and identifiers are nested under `rows[].data`, whose shape is owned by the resource contract.
- Centralize User list filtering, sorting, base query, required relations, and stable tie-breaking in a reusable query definition consumed by both the list and export operations.
- Use `maatwebsite/excel` current 4.x line for spreadsheet reading/writing. Support XLSX initially and retain engine compatibility with CSV.
- Generate the XLSX template from the same User import column contract used by heading validation and row mapping.
- Support English and Persian heading aliases. Normalize Persian/Arabic `ی` and `ک`, whitespace, zero-width characters, punctuation, and diacritics before matching. Slugification may be used for internal comparison but is not the sole heading strategy.
- Limit the first import implementation to one XLSX worksheet, one header row, no macros, no merged cells, and no multi-sheet import semantics.
- Limit uploads to 2,000 data rows and validate synchronously. Queue post-approval local/provider processing as needed.
- Require `identity_key=phone` or `identity_key=email` on preview. Do not silently fall back between identity keys.
- Support create-or-update behavior. Update only supplied fields; empty optional update cells preserve existing values. Identity conflicts reject the row.
- Reject duplicate identities within one file and all conflicting rows rather than applying order-dependent “last row wins” behavior.
- Support an optional local `password` import column. Validate it using the existing account password rules. Empty password preserves the existing password. Never export, log, return, or include passwords in error artifacts or normalized preview results.
- Support additive row-level provider request columns named `provision_<provider>`, initially for providers required by User provisioning. Missing, blank, or false means no operation; true means ensure the provider account exists.
- Keep standalone User provider provisioning behind a provider-user capability contract separate from enrollment provisioning. Import contracts declare supported provider capabilities, while provider operations reuse existing low-level integration clients where appropriate.
- Do not call external providers during preview. Provider operations run only after valid local rows are approved and successfully committed.
- Commit all valid local user creates/updates in one database transaction. If local persistence fails, roll back the transaction and dispatch no provider operations.
- Treat provider provisioning as eventually consistent because external systems cannot participate in the local database transaction. Queue provider work after local commit, track each user/provider outcome independently with idempotency keys and bounded automatic retries, and expose final outcomes through Import Run polling.
- Use an immutable Import Run for the uploaded file, normalized rows, preview result, approval decision, local result, provider results, and artifact references. Approval is idempotent and can succeed only once.
- Use standard resource routes for export, template, import preview, run status, approval, and error download. Approval is always explicit; it requires `include_valid_rows=true` only when invalid rows exist, while an all-valid preview uses an empty approval body.
- Prefer private stored export artifacts and authenticated download references so a connection interruption does not require regenerating the export. Export generation may be queued when storage/processing characteristics require it.
- Export an explicit safe User field allowlist. Translate enums according to the requested locale and format dates as Jalali `Verta` values rather than raw database values.
- Provide separate permissions for export, template download, import preview, import approval, and run-result/error access.
- Record import/export metadata in the administrative audit trail: staff actor, resource, operation, filters, identity key, filename, row counts, status, and failure summary. Exclude passwords, tokens, credentials, and raw sensitive payloads.
- Add dedicated persistence for Import Runs and row-level results. Do not add asynchronous export persistence unless the implementation needs it; stored export artifacts may initially be tracked through the run/download mechanism selected by the package integration.
- Keep the first frontend workflow minimal: upload file, display preview/results, explicitly approve valid rows, and poll the Import Run after approval until provider processing reaches a terminal status. Manual retry UI is not required for the first frontend release.
- Expose future API capabilities in the documented contract: queued preview generation, manual provider retry, CSV templates/output, explicit clear markers, provider disable/delete, relationship/enrollment imports, arbitrary provider-specific fields, recurring imports, and distributed transactions.

## Testing Decisions

- Test at the highest practical seam: admin API feature tests using external fakes for spreadsheet processing, queues, private storage, and provider contracts.
- Tests must assert externally visible HTTP responses, persisted outcomes, dispatched work, authorization behavior, and downloadable artifacts—not internal class structure or implementation details.
- Cover template generation and the supported XLSX heading/column contract.
- Cover English/Persian heading aliases and normalization of Arabic/Persian character variants, zero-width characters, punctuation, whitespace, and diacritics.
- Cover translated enum export values and Verta-formatted Jalali dates.
- Cover safe export allowlisting, including the absence of passwords, tokens, credentials, and provisioning internals.
- Cover valid create rows, valid update rows, explicit identity keys, empty optional update fields, optional password import, and password redaction.
- Cover invalid field values, unsupported identity keys, malformed booleans, duplicate file identities, and conflicts with existing users.
- Cover preview side-effect safety: no local mutation and no provider calls before approval.
- Cover approval rejection without `include_valid_rows=true` when invalid rows exist, empty-body approval for an all-valid preview, idempotent repeated approval, and atomic local rollback on persistence failure.
- Cover provider flag semantics, provider capability declarations, post-commit dispatch, independent provider statuses, retryable failures, bounded retries, and idempotency.
- Cover the approval response's queued provider state and run-status polling until final provider outcomes are available.
- Cover private artifact access, expiry behavior, error report redaction, separate permissions, and audit metadata.
- Cover shared filter/sort behavior by asserting that list and export select the same matching users in the same deterministic order.
- Follow existing admin API feature-test patterns and existing provisioning/provider contract-test patterns. Use Pest, the project authentication trait, and the repository’s parallel test requirements.

## Out of Scope

- Provider account deletion, disabling, or revocation through import.
- Importing enrollments, orders, relationships, courses, or other resources.
- Provider-specific spreadsheet data beyond additive provisioning flags.
- Provider credentials or provider passwords in spreadsheets.
- Distributed all-or-nothing transactions across Laravel and external providers.
- Password export.
- Arbitrary user-defined column mappings.
- Multi-sheet workbooks, macros, formulas, merged-cell semantics, or recurring imports.
- Manual retry UI in the first frontend release.
- Queued preview generation unless synchronous limits prove insufficient.
- CSV template/output in the first release, while the engine remains compatible with CSV.

## Further Notes

The first implementation should preserve the existing API conventions: thin controllers, business logic in Actions, `spatie/laravel-data` DTOs for request/response contracts, `apiResponse()` for API responses, existing authorization conventions, and no Form Requests or API Resources.

The central engine is intentionally a seam for future resources, not a reason to force all resources into a generic data model. Resource-specific contracts remain responsible for domain mapping and business validation, while the engine owns lifecycle, artifacts, approval, and common spreadsheet mechanics.
