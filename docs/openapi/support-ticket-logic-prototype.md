# Support Tickets — Logic Prototype

**Status:** Prototype for review — no production implementation

## Question this prototype answers

Can a small, headless support workflow let an authenticated shop customer submit a request and later see the answer, while staff can find, answer, track, and close it without introducing assignments, forwarding, priorities, attachments, or a general conversation system?

## Scope

The first version has one customer submission and one staff answer. The original customer data remains unchanged after submission. A customer can see only their own tickets; staff can see all tickets permitted by the admin API.

The existing `Vendor` domain is used as the department source because the project already describes vendors as internal departments/external entities. The API can expose this as a department select option without creating a second department concept.

## Proposed ticket data

```text
SupportTicket
  id                 internal numeric identifier
  uuid               public identifier
  customer_id        required users.id, set from authenticated user
  department_id      required vendors.id
  title              required string, max 191
  message            required text
  answer             nullable text
  status             new | answered | closed
  answered_by_id     nullable staff.id
  answered_at        nullable timestamp
  closed_at          nullable timestamp
  created_at
  updated_at
```

Customer identity is resolved from `customer_id`; the API does not accept duplicate name or phone fields. Admin responses can include the related user’s current name and phone through the `customer` relation. `answer` is intentionally a single nullable field; a reply thread is out of scope.

## State model

```text
   NEW ────────────────► ANSWERED ───────► CLOSED
    │                       │                │
    └──────── staff closes ─┘                │
                             staff reopens ──┘
```

Allowed operations:

| Current status | Operation | Result |
|---|---|---|
| `new` | Staff answers with non-empty text | `answered` |
| `new` | Staff closes without answering | `closed` |
| `answered` | Staff closes | `closed` |
| `closed` | Staff reopens | `new` |
| `closed` | Staff answers again | `answered` |
| any | Customer edits original submission | Not allowed |
| any | Customer creates a follow-up | Creates a separate ticket |

Answering a closed ticket replaces the current answer and refreshes `answered_by_id` and `answered_at`. If the product should preserve every answer, that is the point where a separate `support_ticket_replies` table would be introduced; this prototype deliberately does not add it.

## Shop API contract

All responses use the project’s `apiResponse()` envelope and spatie/laravel-data DTOs.

### Department options

```http
GET /api/v1/shop/support-tickets/departments
Authorization: Bearer <customer-token>
```

Returns active departments/vendors suitable for a customer select field.

### Create a ticket

```http
POST /api/v1/shop/support-tickets
Authorization: Bearer <customer-token>
Content-Type: application/json

{
  "department_id": 2,
  "title": "I cannot access my course",
  "message": "I paid for the course but it is not visible in my account."
}
```

The authenticated user supplies `customer_id` server-side. The API rejects `customer_id`, `name`, and `phone` as writable ticket fields.

### Customer ticket list

```http
GET /api/v1/shop/support-tickets
Authorization: Bearer <customer-token>
```

Returns the authenticated customer’s tickets, newest first, with `uuid`, title, department, status, answer presence, and timestamps.

### Customer ticket detail

```http
GET /api/v1/shop/support-tickets/{ticket}
Authorization: Bearer <customer-token>
```

Returns the ticket’s submitted fields, department, status, answer, and timestamps. A ticket belonging to another customer behaves as not found.

## Admin API contract

Admin routes use the existing staff authentication and admin permission conventions.

### List and inspect

```http
GET /api/v1/admin/support-tickets
GET /api/v1/admin/support-tickets/{ticket}
```

The list supports `status`, `department_id`, search across title/message and the related customer’s name/phone, `per_page`, and newest-first sorting. The compact list includes the related customer identity and whether an answer exists.

### Answer

```http
PATCH /api/v1/admin/support-tickets/{ticket}/answer
Content-Type: application/json

{ "answer": "Your payment was confirmed. Access is now enabled." }
```

This operation sets `answer`, `answered_by_id`, `answered_at`, and `status = answered` atomically. Empty answers are rejected.

### Change status

```http
PATCH /api/v1/admin/support-tickets/{ticket}/status
Content-Type: application/json

{ "status": "closed" }
```

Supported values are `new`, `answered`, and `closed`. Closing sets `closed_at`; reopening clears `closed_at` and sets status to `new`. Status changes do not modify the submitted message or customer relation.

## Validation and security rules

- Customer must be authenticated with the `user` guard to create or read tickets.
- Staff must be authenticated with the `staff` guard to access admin endpoints.
- `department_id` must reference an active department/vendor.
- `title` and `message` are required on creation.
- `customer_id` is never accepted from the request body and is always taken from the authenticated user.
- Answer must be non-empty and bounded to a practical text limit, proposed at 10,000 characters.
- Customers cannot change status, answer, department, title, or message after creation.
- No public ticket lookup by phone, title, or sequential ID.
- Every admin mutation goes through the existing admin audit middleware/logging.
- Repeated answer/status requests should be safe: the final stored state is authoritative and no duplicate ticket is created.

## Minimal implementation shape

1. `SupportTicket` model, factory, status enum, and migration.
2. `CreateSupportTicketAction` for shop creation.
3. `AnswerSupportTicketAction` and `ChangeSupportTicketStatusAction` for admin mutations.
4. Shop/admin DTOs and thin controllers.
5. Policies for customer ownership and staff permissions.
6. Focused Pest feature coverage for ownership, validation, answer transitions, close/reopen, and response shapes.

No notifications, queues, assignments, forwarding, attachments, SLA timers, priorities, tags, or reply tables are part of this prototype.

## Prototype verdict

The smallest coherent model is a single `support_tickets` record with one nullable staff answer and a three-state lifecycle: `new`, `answered`, and `closed`. This is enough for a headless shop/admin flow and keeps the future complexity boundary explicit: if multiple answers or customer follow-ups are required, add a replies table rather than expanding the ticket row with more answer fields.
