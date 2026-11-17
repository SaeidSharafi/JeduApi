# Student Enrollment Detail — Frontend Guide

This guide covers the authenticated course and seminar enrollment detail endpoints, including the IMS grade report and the existing survey, Moodle, and certificate behavior.

## Endpoints and access

Use the storefront's existing authenticated API client, including its user session/cookie handling. These routes are inside the `auth.cookie:user` and `auth:user` middleware group; they do not require an IMS credential or a new authentication flow.

| Purpose | Method | Endpoint |
| --- | --- | --- |
| List the signed-in student's course enrollments | `GET` | `/api/v1/shop/student/courses` |
| Read one course enrollment | `GET` | `/api/v1/shop/student/courses/{enrollmentUuid}` |
| List the signed-in student's seminar enrollments | `GET` | `/api/v1/shop/student/seminars` |
| Read one seminar enrollment | `GET` | `/api/v1/shop/student/seminars/{enrollmentUuid}` |

`{enrollmentUuid}` is the enrollment's `uuid`, not a product UUID, slug, course code, or IMS enrollment ID. Detail reads return `404` when the enrollment belongs to another customer or has the wrong product type. Treat those cases identically.

Success responses use the standard envelope `{message, data, metadata}`. The detail payload is `data`; `data.grade_info` is added to the existing detail contract. List responses retain their existing `EnrollmentData` shape: they do not include `grade_info` or `survey_block`. Fetch the detail endpoint to render the grade panel and survey state.

## IMS grade report

Render the report only from `data.grade_info`. The grade report contains no student or enrollment identifiers. Use the enrollment UUID with the existing authenticated shop API client; Jedu resolves IMS identifiers and credentials server-side. Other existing response fields, including list `provisioning_data`, remain separate and may contain provider references. Do not use those references to call IMS.

```json
{
  "message": "Operation successful.",
  "data": {
    "uuid": "01a07b30-3c69-719c-8c81-cda76a3b2575",
    "grade_info": {
      "state": "available",
      "report": {
        "status": "passed",
        "status_translated": "Passed",
        "items": [
          {
            "name": "final",
            "label": "Final",
            "value": 80,
            "pass_grade": 60,
            "is_passed": true
          }
        ]
      }
    }
  },
  "metadata": []
}
```

The example shows the complete `grade_info` shape; other enrollment detail fields are omitted. The full course and seminar response examples are [course detail](../../resources/responses/shop/courses/show.json) and [seminar detail](../../resources/responses/shop/seminars/show.json).

### Availability states

`state` is a raw string enum. Use it to decide whether the IMS report can be shown; do not derive a state from item values.

| `state` | `report` | Frontend behavior |
| --- | --- | --- |
| `not_supported` | `null` | Hide the IMS grade panel. This enrollment has no IMS grade-report reference/configuration. |
| `locked` | `null` | Keep grade details hidden. Survey results may still be locked, or enrollment access may be future, inactive, or revoked. |
| `pending` | `null` | Show a “not recorded yet” state. IMS returned status `none`, meaning no configured grade field has a recorded value. |
| `available` | Report object | Render the supplied report, including partial reports. |
| `unavailable` | `null` | Show a temporary/unavailable state. This can mean the integration is unready, the reference is missing, the response is invalid, or IMS/network returned an error. Do not present it as “no grade.” |

The report's own `status` has a separate meaning from `grade_info.state`:

| Report `status` | Meaning |
| --- | --- |
| `passed` | Reported grades meet the configured requirements. |
| `rejected` | At least one reported grade is below its configured passing threshold. The report is still available and should be shown. |
| `incomplete` | Some grades are recorded and one or more required grade fields remain missing. The partial report is still available and should be shown. |
| `none` | No grade field has a recorded value. Jedu maps this to `state: "pending"` and `report: null`. |

Each `items[]` entry contains only configured fields:

| Field | Type | Rendering guidance |
| --- | --- | --- |
| `name` | string | Stable grade field key. |
| `label` | string | Display this label for the field. |
| `value` | number or `null` | Render numeric values, including `0`; `null` means no value was recorded for this field. Decimal values are valid. |
| `pass_grade` | number or `null` | Optional passing threshold; it may be fractional. |
| `is_passed` | boolean or `null` | Use the server's result. `null` means there is no pass decision for this field, such as when no threshold is configured or no value exists. |

Display `status_translated` for the report summary and `label` for item names. Do not calculate pass/fail from `value` and `pass_grade`; the server applies course-specific thresholds and overrides. A report with status `rejected` is a valid report, not an API error.

### Refresh behavior

An eligible, unlocked IMS-backed detail read fetches the current report when its persisted IMS reference, student identity, and ready integration are available. Other cases skip the IMS request and return the appropriate state above. After a read returns `pending` or `unavailable`, refresh the enrollment detail on page return/focus or an explicit user retry. If grades are being entered asynchronously, use only a short bounded retry after focus; avoid continuous polling. Never use `provisioning_data` or Moodle grades as a fallback for an absent IMS report.

The backend dependency is deployed in two repositories: Jedu API commit `acbbcab5` calls IMS commit `a9691100`'s `GET /api/v2/enrollment/{id}/grade` endpoint. Jedu attaches the IMS enrollment ID and civil identity to that upstream request; the storefront sends only the enrollment UUID to Jedu.

Deploy the IMS endpoint before rolling out the Jedu API/frontend. If an eligible detail read reaches Jedu before that endpoint is available, it returns `grade_info.state: "unavailable"`; the rest of the enrollment detail still loads.

## Survey state and grade locking

`data.survey_block` describes the survey configured for this exact enrollment's product delivery option (PDO). Use `results_locked` as the survey-results gate; do not infer it from `enrollment_status` or from whether a URL exists.

`survey_block.state` is a raw string enum. `enrollment_status` uses a different representation: an object with `value` and translated `label`.

| `survey_block.state` | Meaning | `results_locked` |
| --- | --- | --- |
| `not_required` | This product/PDO does not require a survey. An optional survey URL may still be present. | `false` |
| `awaiting_survey` | Survey is required, but no URL is configured for this PDO. The block may include an explanatory `message`. | `true` |
| `pending` | Survey is required and not yet completed. A URL may be present when access permits; it can be `null` while access is future, inactive, or revoked. | `true` |
| `completed` | The verified survey webhook recorded completion for this enrollment. | `false` |

Show the required-survey call to action only when `survey_block.results_locked` is `true` and `survey_block.url` is non-null. Use the returned URL exactly as supplied. It is an external survey URL with an opaque per-enrollment bearer token in its query string: do not rebuild it, strip or rewrite its query parameters, or send the URL/token to analytics, logs, or browser error reporting. Optional survey URLs can appear with `state: "not_required"`; they are not required completion gates.

The browser never marks a survey complete. Porsline posts the submission to the API's authenticated-by-secret webhook; the server records completion for that enrollment and clears its student quiz cache. When the student returns from the survey, refetch detail on window focus and offer a manual refresh if the webhook has not arrived yet. Once a fresh detail response shows `completed`, invalidate/refetch any client-side course/seminar list or quiz caches that display survey state or result values. A short bounded retry may cover webhook delivery delay. Completion changes no enrollment access or provisioning state.

While `results_locked` is true, the API hides Moodle course grades and quiz activity scores. The student may still use available learning/access features: render those using their existing access fields, including `delivery_access.is_ready`, rather than disabling the entire course UI. IMS grade reports have an additional eligibility check, so `grade_info.state` can be `locked` even when the survey itself is complete or not required.

## Moodle grades and quizzes

Moodle data remains a separate, existing contract:

- `delivery_access.course_grade` is the cached Moodle course grade. It is `null` while survey results are locked.
- `quizzes[]` contains Moodle activity details; each quiz's `score` is `null` while survey results are locked.
- Student course/seminar list responses redact Moodle grade values in `provisioning_data` while a required survey is incomplete. The live quiz endpoint applies the matching-enrollment redaction as well.
- `grade_info.report` is the IMS grade report. Do not substitute it for Moodle values or use Moodle values when the IMS report is pending/unavailable; do not use raw provisioning data to bypass the server's redaction.

Keep access controls independent from grade display. `delivery_access.is_ready`, SSO, join, download, and quiz access keep their existing behavior; `results_locked` hides result values, not the student's whole learning area.

## Certificate block

`certificate_info` contains `provides_certificate`, `is_available`, and `certificate_url`. `is_available` expresses capability/eligibility under the current survey/access rules, but certificate retrieval is not implemented and `certificate_url` is currently always `null`. Certificate retrieval is deferred to the pending Rouyeh API integration; this API defines no certificate download endpoint. Enable a download action only when both `is_available` is `true` and a future API returns a non-empty `certificate_url`.

## Frontend validation checklist

- Load each detail route by the enrollment UUID through the authenticated storefront API client. A foreign enrollment and wrong product type both render the normal not-found state.
- Show an IMS report for `available`, including `passed`, `rejected`, `incomplete`, zero values, fractional values/thresholds, and items with `pass_grade: null` / `is_passed: null`.
- Show no IMS values for `pending`, `locked`, `unavailable`, or `not_supported`; keep those states visually distinct.
- For required surveys, offer the supplied survey link only when both `results_locked` and `url` allow it. Refetch detail after returning; do not mutate completion from the browser.
- Under a survey lock, confirm Moodle grade/quiz scores remain hidden while normal learning/access controls still follow their existing `is_ready` and access rules.
- Do not expose a certificate download while `certificate_url` is null.

## Backend references

- [Student course detail controller](../../app/Http/Controllers/Api/Shop/Student/CourseController.php)
- [Student seminar detail controller](../../app/Http/Controllers/Api/Shop/Student/SeminarController.php)
- [Enrollment detail action](../../app/Actions/Shop/Student/GetEnrollmentDetailAction.php)
- [Survey completion policy](../../app/Actions/Shop/Student/EnrollmentSurveyPolicyAction.php)
- [Grade state enum](../../app/Enums/EnrollmentGradeStateEnum.php)
- [Grade report DTO](../../app/Data/Shop/Student/Enrollment/EnrollmentGradeReportData.php)
