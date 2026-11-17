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

## Submission webhook

Configure a Porsline webhook to send each completed submission as `POST` JSON to:

```text
https://<api-host>/api/v1/shop/webhooks/porsline-surveys
```

Add the static header `X-Survey-Webhook-Secret` in Porsline. Set the matching application deployment variable `PORSLINE_WEBHOOK_SECRET` to a private value, then rebuild the Laravel configuration cache using the deployment's normal process. The application rejects submissions when the variable or header is missing, or when the values do not match. Do not put this secret in the survey URL or commit it to source control.

The JSON body must contain a `variables` list of `{ "name": ..., "value": ... }` entries with exactly one nonempty, 64-character lowercase hexadecimal `enrollment_token`. The other four reporting variables may also be included. Porsline's optional `questions`, `computational_variables`, and `respondent_data` fields are accepted when present but are not stored by Jedu API.

```json
{
  "variables": [
    { "name": "enrollment_token", "value": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" },
    { "name": "product_slug", "value": "example-course" },
    { "name": "product_name", "value": "Example course" },
    { "name": "course_name", "value": "Example course name" },
    { "name": "product_type", "value": "course" }
  ]
}
```

The endpoint has no student-session authentication; the static header authenticates Porsline independently from the enrollment token. A successful response uses the standard API envelope with `data: null`. Missing, malformed, duplicate, or unknown tokens are rejected. The first accepted submission records completion for only the matching Enrollment; retries succeed without changing the first completion timestamp. Completion does not change enrollment access, status, survey configuration, or Moodle progress.
