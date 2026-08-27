# Admin Inbound Request Inboxes

## Goal

Expose Contact Requests and Collaboration Requests as separate staff inboxes. Staff can list and inspect immutable submissions, assign responsibility, record an internal note, and update follow-up status.

## Domain language

- **Contact Request**: a public contact-form submission.
- **Collaboration Request**: a public collaboration-form submission, optionally with an attachment.
- **Inbound Request Status**: `pending`, `contacted`, `resolved`, or `no_response`.
- **Assigned Staff Member**: the staff member currently responsible for follow-up.

`AdviceRequestStatusEnum` has already been renamed to `InboundRequestStatusEnum`; its backed values and API representation remain unchanged.

## Scope

### Contact Request endpoints

- `GET /api/v1/admin/contact-requests`
- `GET /api/v1/admin/contact-requests/{contactRequest}`
- `PATCH /api/v1/admin/contact-requests/{contactRequest}/status`
- `PATCH /api/v1/admin/contact-requests/{contactRequest}/assignment`
- `PATCH /api/v1/admin/contact-requests/{contactRequest}/note`

### Collaboration Request endpoints

- `GET /api/v1/admin/collaboration-requests`
- `GET /api/v1/admin/collaboration-requests/{collaborationRequest}`
- `PATCH /api/v1/admin/collaboration-requests/{collaborationRequest}/status`
- `PATCH /api/v1/admin/collaboration-requests/{collaborationRequest}/assignment`
- `PATCH /api/v1/admin/collaboration-requests/{collaborationRequest}/note`

Submitted identity and message fields are immutable through admin APIs. Creation remains available only through the existing public form endpoints. Deletion is out of scope.

## Workflow data

Add the following fields to both request tables:

- `status`: indexed string, default `pending`, cast to `InboundRequestStatusEnum`.
- `note`: nullable text; API validation permits explicit `null` or at most 1,000 characters.
- `assigned_to_id`: nullable, indexed foreign key to `staff.id`, set null when the staff record is deleted.

This project is still in development. Modify the two original create-table migrations rather than adding an alter-table migration:

- `2025_09_12_105821_create_contact_us_requests_table.php`
- `2025_09_12_110245_create_collaboration_requests_table.php`

Rebuild and verify the schema with `vendor/bin/sail artisan migrate:fresh --no-interaction`.

No additional workflow timestamps are required. Existing `updated_at` and admin audit logs cover current needs.

## Authorization

Declare permissions only through `config/permission-generator.php`, then regenerate `PermissionEnum` and synchronize the staff guard.

Each resource receives:

- `view_any`
- `view`
- `update`
- `update_own`

Policy rules:

- `view_any` permits listing all records of that resource.
- `view` permits viewing an individual record.
- `update` permits status, note, and assignment changes on any record and assignment to any non-banned staff member.
- `update_own` permits claiming an unassigned record for oneself, updating status/note while assigned, and unassigning oneself.
- `update_own` never permits taking over or changing a record assigned to someone else.

The staff select-option endpoint must exclude banned staff by default. Assignment validation must also reject banned staff.

## Read contracts

List responses are paginated and compact:

- Common fields: ID, full name, nullable phone/email, translated status, assigned staff, and creation time.
- Contact Request: subject.
- Collaboration Request: nullable department and `has_attachment`.

Detail responses include all submitted fields, status, note, assigned staff, and timestamps. Collaboration Request details also include private attachment metadata.

The existing `PrivateFileDownloadController` and its `files.view_any` permission remain the single private-download mechanism. No resource-specific attachment endpoint is required.

Legacy nullable phone/email values must remain representable in admin DTOs.

## Listing behavior

Both lists support:

- Exact filters: status and assigned staff.
- Search across full name, phone, and email.
- Sorts: creation time, status, and assigned staff.
- Default sort: newest first.
- Standard `per_page` pagination.

Contact Requests additionally support subject filtering/search. Collaboration Requests additionally support department filtering.

## Mutation contracts

- Status endpoint requires a valid `InboundRequestStatusEnum` value. Transitions are unrestricted, including reopening a resolved request.
- Assignment endpoint requires a `staff_id` key. A non-banned staff ID assigns; explicit `null` unassigns.
- Note endpoint requires a `note` key. A string updates the note; explicit `null` clears it.
- Each endpoint changes only its named workflow field.

## Staff database notifications

Add Laravel database-notification storage and a generic personal staff notification API:

- `GET /api/v1/admin/notifications` with newest-first pagination and unread filtering.
- `GET /api/v1/admin/notifications/unread-count`.
- `PATCH /api/v1/admin/notifications/{notification}/read`, returning 204.
- `PATCH /api/v1/admin/notifications/read-all`, returning 204.

Authenticated staff may access only their own notifications; no generated notification permission is needed. Notification records expose ID, type, title, message, resource type, resource ID, read timestamp, and creation timestamp. No delete or pruning behavior is required.

Queue a database notification when a request is assigned or reassigned to a different staff member. Do not notify on unassignment, do not broadcast new submissions, and do not notify a staff member who assigns or claims the request for themselves.

## API documentation

Use Scribe-compatible controller and DTO documentation. Add manual query/body parameter documentation only when Laravel Data inference cannot accurately represent the contract. Provide response examples under the established `resources/responses/admin/<resource>/` layout.

## Verification

- Pest feature tests for list/detail authorization, filtering, sorting, pagination, and response shapes for both resources.
- Pest feature tests for broad update and `update_own` self-claim boundaries.
- Validation tests for enum values, note length/null clearing, assignment nullability, and banned staff.
- Attachment metadata/download integration coverage.
- Staff selector coverage proving banned staff are excluded.
- Database notification dispatch, ownership, unread filtering/count, and mark-read coverage.
- Run focused tests, relevant parallel suites, `vendor/bin/sail bin pint --dirty --format agent`, and Scribe generation verification.

## Implementation order

1. Keep the isolated enum rename commit already completed.
2. Implement and commit the two request inbox workflows, original migration edits, permissions, API documentation, and tests.
3. Implement and commit database notification storage, dispatch, staff notification APIs, documentation, and tests.
