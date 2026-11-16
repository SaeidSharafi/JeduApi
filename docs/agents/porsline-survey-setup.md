# Porsline enrollment survey variables

For each Course or Seminar Product Delivery Option, configure its own Porsline form URL. Course/Seminar settings belong to the exact delivery option, so two delivery options may share one form or use separate forms. A configured URL can be offered even when the survey requirement is disabled; the requirement controls result locking independently.

In Porsline, define these custom variables with the exact lowercase names below and make them available in the response report:

| Variable | Value supplied by Jedu API |
| --- | --- |
| `enrollment_token` | Random opaque token for this Enrollment; use this value as the correlation key. |
| `product_slug` | Final catalog Product slug. |
| `product_name` | Readable catalog Product name. |
| `course_name` | Readable Course or Seminar `full_name`. |
| `product_type` | `course` or `seminar`. |

The student detail link appends those values to the exact Product Delivery Option's configured URL. It URL-encodes values, preserves unrelated query parameters such as UTM attribution and any fragment, and replaces any pre-existing values for these five names. The Product slug and names are reporting context only; use `enrollment_token` to correlate a response. Do not add database IDs or an enrollment UUID to the form URL.

Example variable names in a shared Porsline form: `enrollment_token`, `product_slug`, `product_name`, `course_name`, `product_type`.

Opening or returning from a survey does not complete an Enrollment. A copied complete link remains a bearer link: the opaque token prevents guessing from public identifiers, but anyone who receives the full link can submit against that Enrollment.
