# Frontend Developer Guide — Audit System (Admin API)

Frontend-facing guide for the 4 audit/compliance endpoints. Explains routes, required fields,
complete response field lists, variations, and how to display them.

## Base URL & Authentication

- Base URL: `{APP_URL}/api/v1/admin` (dev: `http://jedu.test/api/v1/admin`)
- Authentication: Bearer token of a **staff** account (`Authorization: Bearer <staff_token>`). The
  `auth:staff` guard is applied automatically. No token → `401 Unauthenticated`.
- Required permissions (assigned via roles):
  - `audits.admin_actions_view` — list + show admin action logs
  - `audits.compliance_reports_view` — generate compliance report
  - `audits.suspicious_activity_view` — detect suspicious activity
  - Missing permission → `403 Forbidden`.

## Response Envelope (all endpoints)

Every successful response has the same shape:

```json
{
  "message": "…",        // human-readable (Persian) message string
  "data": { … },         // payload — shape differs per endpoint
  "metadata": []         // reserved, currently always []
}
```

- Dates in payloads are **Jalali** strings, format `YYYY-MM-DD HH:MM:SS` (or `YYYY-MM-DD` for date-only fields).
- Amounts are in **IRR (Rial)** integers. Do not add decimals.
- Validation errors → `422` with `message` + `errors` object keyed by field name.

---

## 1. GET `audit/admin-actions` — Admin action log list

Route name: `api.v1.admin.audit.admin-actions.index` · `GET /api/v1/admin/audit/admin-actions`

Paginated list of staff actions, newest first.

### Query parameters

| Parameter | Type | Description |
|---|---|---|
| `filter[admin_id]` | int | Exact staff ID filter. |
| `filter[action_type]` | string | Exact action type: `create`, `update`, `delete`, `login`, … |
| `filter[resource_type]` | string | Exact resource type (model name), e.g. `Wallet`. |
| `filter[risk_level]` | string | Exact risk level: `low`, `medium`, `high`. |
| `filter[http_method]` | string | Exact HTTP method: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`. |
| `filter[response_status]` | int | Exact HTTP status, e.g. `200`, `422`. |
| `filter[route_name]` | string | Partial (contains) match on route name. |
| `filter[ip_address]` | string | Exact IP match. |
| `filter[date_from]` | string | `created_at >= value` (`Y-m-d H:i:s`). |
| `filter[date_to]` | string | `created_at <= value` (`Y-m-d H:i:s`). |
| `filter[search]` | string | Free text: matches route name **or** admin name. |
| `sort` | string | One of `created_at`, `admin_id`, `action_type`, `risk_level`, `response_status`. Prefix `-` for descending (e.g. `-created_at`). Default: `-created_at`. |
| `per_page` | int | Items per page (default `app.page_size`, usually 15). |

**Variations:** Filters may be combined freely. Spatie exact filters also accept
comma-separated values, e.g. `filter[risk_level]=low,medium`.

### Response `data` structure

```
data (paginator)
├── current_page, last_page, per_page, total, from, to
├── first_page_url, last_page_url, next_page_url, prev_page_url, path
├── links[]                 { url, label, active } pagination buttons
└── data[]                  list item (AdminAuditLogListData)
    ├── id                  int — log id
    ├── admin               object — staff who performed the action
    │   ├── id, name, email, phone
    │   ├── created_at, updated_at   Jalali datetime (nullable)
    │   ├── is_admin        bool|null
    │   └── roles           array|null — role objects (may be null)
    ├── route_name          string|null — e.g. "api.v1.admin.digital-asset.store"
    ├── action_type         string — create | update | delete | login | …
    ├── resource_type       string|null — model name ("Wallet", "User", …)
    ├── resource_id         int|null — id of the affected record
    ├── http_method         string — POST, PUT, DELETE, …
    ├── response_status     int — HTTP status of the original request
    ├── ip_address          string — client IP
    ├── risk_level          string — low | medium | high
    ├── created_at          Jalali datetime
    └── action_summery      string — human label, e.g. "Create Wallet #12"
```

### Display notes

- `action_summery` is ready-made for table "what happened" column (note the project's spelling).
- Color-code `risk_level`: low → green, medium → amber, high → red.
- Show `response_status`; `>= 400` means the request **failed** — still audited.
- Default `per_page` is 15; implement paging with the `links`/`total` fields.
- Dates arrive Jalali — render directly, do not reformat.

---

## 2. GET `audit/admin-actions/{adminActionLog}` — Admin action log detail

Route name: `api.v1.admin.audit.admin-actions.show` · `GET /api/v1/admin/audit/admin-actions/{id}`

Full detail of one log entry.

### Response `data` structure

```
data
├── id                    int
├── admin                 object — same shape as list item admin (ShowStaffData)
├── action_type           string
├── resource_type         string|null
├── resource_id           int|null
├── route_name            string
├── http_method           string
├── request_data          object|null — sanitized request payload sent by the admin
│                          (sensitive fields like passwords/tokens are redacted)
├── response_status       int
├── ip_address            string
├── user_agent            string|null
├── session_id            string|null
├── risk_level            string
├── metadata              object|null — server-side measurement
│   ├── timestamp         ISO-8601 UTC
│   ├── memory_usage      int bytes
│   ├── request_size      int bytes
│   ├── response_size     int bytes
│   └── execution_time_ms float
├── created_at            Jalali datetime
├── resource              object|null — the affected model (morph), if still exists
│                          and its type is resolvable; else null
└── action_summery        string
```

### Display notes

- `request_data` may be `null` and is **always sanitized** (passwords, tokens, secrets removed) — render as an expandable key/value tree or JSON pretty-print.
- `resource` is the linked record snapshot (e.g. the created `DigitalAsset`); may be `null` when the record was deleted or is non-model metadata.
- Use this view for an "audit detail" modal/route with a raw-JSON toggle for `request_data` and `metadata`.

---

## 3. POST `audit/compliance-report` — Generate compliance report

Route name: `api.v1.admin.audit.compliance-report` · `POST /api/v1/admin/audit/compliance-report`

Financial compliance report over a date range. **Dates are Jalali (`Y-m-d`).**

### Request body

| Field | Type | Required | Default | Rules |
|---|---|---|---|---|
| `date_from` | string `Y-m-d` Jalali | yes | — | must be ≤ `date_to` |
| `date_to` | string `Y-m-d` Jalali | yes | — | must be ≤ today |
| `report_type` | string | no | `daily` | `daily` \| `monthly` \| `custom` |
| `user_ids` | int[] | no | null | existing user ids |
| `transaction_types` | string[] | no | null | e.g. `["deposit","withdrawal"]` |
| `min_amount` | int | no | null | `>= 0` |
| `max_amount` | int | no | null | `> min_amount` |
| `include_transaction_analysis` | bool | no | `true` | |
| `include_admin_activity` | bool | no | `true` | |
| `include_suspicious_activity` | bool | no | `true` | |
| `include_risk_assessment` | bool | no | `false` | |

**Variations:** Setting any `include_*` to `false` removes the matching `report_sections.*`
section. `report_type=daily` additionally adds `report_sections.daily_breakdown`.

### Response `data` structure

```
data
├── report_period
│   ├── from          Jalali date
│   ├── to            Jalali date
│   └── type          daily | monthly | custom
├── summary                       (always present)
│   ├── total_transactions        int
│   ├── total_volume_rial         int (signed sum — net volume)
│   ├── unique_users              int
│   ├── credits_count             int  (amount > 0)
│   ├── debits_count              int  (amount < 0)
│   ├── credits_volume            int
│   ├── debits_volume             int (absolute value, positive)
│   ├── large_transactions_count  int (|amount| ≥ 5,000,000)
│   └── avg_transaction_amount    float (mean of |amount|)
├── transaction_analysis          (always present at top level)
│   ├── by_type                   { "deposit": {count, volume}, … }
│   ├── by_source                 { "source_type": {count, volume}, … }
│   └── high_risk_transactions    int (count of high-risk flagged)
└── report_sections               (conditional — controlled by include_* flags + report_type)
    ├── transaction_analysis      { by_type, by_source, high_risk_transactions }
    │                              (same as top-level, included when include_transaction_analysis)
    ├── admin_activity            (when include_admin_activity)
    │   ├── total_admin_actions   int
    │   ├── unique_admins         int
    │   ├── by_action_type        { "create": count, … }
    │   ├── by_risk_level         { "low": count, "medium": count, "high": count }
    │   └── failed_actions        int (response_status ≥ 400)
    ├── suspicious_activity       (when include_suspicious_activity)
    │   ├── large_transactions        int (|amount| ≥ 50,000,000)
    │   ├── off_hours_transactions    int (< 06:00 or > 22:00 and |amount| ≥ 5,000,000)
    │   ├── high_frequency_users      int (users with ≥ 50 tx in range)
    │   └── round_number_transactions int (|amount| % 1,000,000 == 0 and ≥ 1,000,000)
    ├── risk_assessment           (when include_risk_assessment)
    │   ├── overall_risk_score    int 0–100
    │   ├── risk_factors          { category → { counts, percentages, risk_level } }
    │   │   ├── transaction_volume_risk
    │   │   ├── temporal_risk
    │   │   ├── pattern_risk
    │   │   └── admin_activity_risk
    │   └── recommendations       [ { priority, category, message, action }, … ]
    └── daily_breakdown           (only when report_type=daily)
        └── { "1404-06-15": { total_transactions, total_volume, unique_users, admin_initiated }, … }
```

### Display notes

- `report_period.from/to` are the **requested** dates — render in the report header.
- Top-level `summary` + `transaction_analysis` are the KPI cards; `report_sections` are the detailed tabs/charts.
- `total_volume_rial` is a signed sum — a negative value means net outflow.
- `debits_volume` is returned as a positive number.
- `by_type` / `by_source` keys are dynamic — iterate them, don't hardcode labels.
- `daily_breakdown` keys are Jalali dates; `admin_initiated` counts admin-triggered transactions that day.
- `risk_assessment.recommendations[].message` is a localized string — display as-is; `priority` is `critical | high | medium | low`.

---

## 4. POST `audit/suspicious-activity` — Detect suspicious activity

Route name: `api.v1.admin.audit.suspicious-activity` · `POST /api/v1/admin/audit/suspicious-activity`

Detects 6 suspicious patterns in wallet transactions. **Dates are Jalali (`Y-m-d`).**

### Request body

| Field | Type | Required | Default | Rules |
|---|---|---|---|---|
| `date_from` | string `Y-m-d` Jalali | yes | — | ≤ `date_to` |
| `date_to` | string `Y-m-d` Jalali | yes | — | ≤ today |
| `large_amount_threshold` | int | no | `50000000` | `>= 1000000` |
| `high_frequency_threshold` | int | no | `10` | 5–100 tx/day |
| `include_off_hours` | bool | no | `true` | |
| `include_large_amounts` | bool | no | `true` | |
| `include_high_frequency` | bool | no | `true` | |
| `include_round_numbers` | bool | no | `true` | |
| `user_ids` | int[] | no | null | existing user ids |

**Variations:** When an `include_*` flag is `false`, the matching collection is **`null`**
(not `[]`) — guard the frontend with null checks. `rapid_succession` and
`unusual_admin_activity` are **always present** (possibly empty arrays).

### Response `data` structure

```
data
├── detection_period
│   ├── from    Jalali date
│   └── to      Jalali date
├── detection_criteria
│   ├── large_amount_threshold    int (echo of request)
│   └── high_frequency_threshold  int (echo of request)
├── suspicious_activities
│   ├── rapid_succession          item[] — ≥2 tx ≥10M within 5 min (always present)
│   ├── unusual_admin_activity    item[] — admin-initiated tx ≥20M (always present)
│   ├── large_transactions        item[]|null  — |amount| ≥ threshold
│   ├── off_hours_transactions    item[]|null  — outside 06:00–22:00 and |amount| ≥ 5M
│   ├── high_frequency_users      item[]|null  — aggregated per user/day ≥ threshold
│   └── round_number_patterns     item[]|null  — |amount| % 1M == 0 and ≥ 5M
└── summary
    ├── total_suspicious_activities   int (sum across all categories)
    ├── by_type                       { category_name: count, … }
    ├── high_risk_count               int (always 0 currently)
    └── unique_users_involved         int
```

### `item` — SuspiciousActivityData

Transaction-level categories (`rapid_succession`, `large_transactions`,
`off_hours_transactions`, `round_number_patterns`, `unusual_admin_activity`):

| Field | Type | Description |
|---|---|---|
| `transaction_id` | int | wallet transaction id |
| `user_id` | int | user id |
| `user_name` | string | `first_name last_name` |
| `amount` | int | signed amount (IRR) |
| `type` | string | transaction type (`deposit`, `withdrawal`, …) |
| `created_at` | string | `YYYY-MM-DD HH:MM:SS` |
| `hour` | string | hour of day (0–23) |
| `flags` | string | **JSON-encoded string** array, e.g. `"[\"large_amount\"]"` — `JSON.parse` it |
| `admin_initiated` | string | `"true"` / `"false"` — **string**, not boolean |
| `ip_address` | string|null | from audit metadata (only present for real transactions) |
| `transaction_count` | int|null | (high-frequency only) |
| `total_volume` | int|null | (high-frequency only) |
| `first_transaction` | string|null | (high-frequency only) |
| `last_transaction` | string|null | (high-frequency only) |
| `avg_transaction_amount` | string|null | (high-frequency only) |
| `pattern` | string|null | human explanation (rapid_succession, unusual_admin_activity only) |

**Aggregated rows** (`high_frequency_users`) differ: `transaction_id = 0`, `amount = 0`,
`type = ""`, `created_at = ""`, `hour = ""`, `admin_initiated = "false"`, `ip_address = null`,
and the meaningful fields are `transaction_count`, `total_volume`, `first_transaction`,
`last_transaction`, `avg_transaction_amount`.

### Display notes

- **Always `JSON.parse(flags)`** before rendering badges — flags is a JSON string like
  `"[\"off_hours\"]"`. Known flag values: `rapid_succession`, `unusual_admin_activity`,
  `large_amount`, `off_hours`, `high_frequency`, `round_numbers`.
- `admin_initiated` is a string — compare with `"true"`, not `true`.
- `high_frequency_users` rows are per user+day aggregates (a "user did N tx on one day"),
  not individual transactions — render in a separate table with the count/volume columns.
- Category names in `summary.by_type` are the collection keys above; `total_suspicious_activities`
  is their sum.
- Thresholds echoed in `detection_criteria` are useful to display as "detection rules used".

---

## 5. UI Structure & Component Type Suggestions

Recommended component types (not literal components) for rendering each endpoint, including the
ECharts chart type that fits each data shape.

### 5.1 Admin action log list (`GET audit/admin-actions`)

```
Page: Admin Actions List
├── PageHeader          title + subtitle ("Administrative Audit Logs")
├── FilterBar (form)
│   ├── Input        filter[search]
│   ├── Select       filter[action_type]       (create/update/delete/…)
│   ├── Select       filter[risk_level]        (low/medium/high)
│   ├── Select       filter[http_method]
│   ├── DatePicker   filter[date_from] / filter[date_to]
│   └── Button       Apply / Reset
├── DataTable
│   ├── Col: action_summery       → link → detail view (Endpoint 5.2)
│   ├── Col: admin.name           → Avatar + name
│   ├── Col: action_type          → Tag/Badge (colored per type)
│   ├── Col: resource_type        → plain text (muted if null)
│   ├── Col: http_method          → MonoBadge (GET green / POST blue / DELETE red / PUT amber)
│   ├── Col: response_status      → StatusChip (2xx green, 3xx blue, 4xx amber, 5xx red)
│   ├── Col: risk_level           → LevelBadge (low green, medium amber, high red)
│   └── Col: created_at           → formatted Jalali datetime
└── Pagination        standard pager from links[]
```

Chart suggestion: **none** for the list itself — it's a log table. If a dashboard wants a
"actions per day" trend, that requires a new aggregated endpoint; do not compute it client-side
from a single page.

### 5.2 Admin action log detail (`GET audit/admin-actions/{id}`)

```
Drawer / Modal: Action Detail   (opened from list row)
├── DrawerHeader
│   ├── action_summery          → Title
│   ├── risk_level              → LevelBadge
│   └── created_at              → muted datetime
├── DescriptionList (2-col info grid)
│   ├── Admin (name + email)  ·  route_name (mono, copyable)
│   ├── http_method + response_status   → MethodBadge + StatusChip
│   ├── ip_address             ·  user_agent (truncate w/ tooltip)
│   └── session_id             → mono, copyable
├── StatRow (4 mini KPI cards)   ← from metadata
│   ├── execution_time_ms      → Stat ("59.8 ms")
│   ├── memory_usage           → Stat ("4 MB")
│   ├── request_size           → Stat ("774 B")
│   └── response_size          → Stat ("312 B")
├── JsonViewer (collapsible, read-only, pretty-printed)
│   └── request_data           → syntax-highlighted JSON
├── ResourcePreview
│   └── resource               → small card (type + id) or JsonViewer if object
└── Footer: close + "view in full page" (optional)
```

Chart suggestion: **none** — single record. Metadata reads best as stat cards, not charts.

### 5.3 Compliance report (`POST audit/compliance-report`)

```
Page: Compliance Report
├── ReportHeader
│   ├── report_period.from → to     → Title (Jalali dates)
│   └── report_period.type          → Tag (daily/monthly/custom)
├── KpiCardRow (summary — 8 cards)
│   ├── total_transactions      → KpiCard
│   ├── total_volume_rial       → KpiCard (format rial; sign-aware — show ⤵ red if negative)
│   ├── unique_users            → KpiCard
│   ├── credits_count           → KpiCard (+)
│   ├── debits_count            → KpiCard (−)
│   ├── credits_volume          → KpiCard
│   ├── debits_volume           → KpiCard
│   └── avg_transaction_amount  → KpiCard
├── Section: Transaction Analysis
│   ├── ECharts Pie   (donut)     data: transaction_analysis.by_type  → count share per type
│   ├── ECharts Bar              data: transaction_analysis.by_type  → volume per type
│   ├── ECharts Pie   (donut)     data: transaction_analysis.by_source → count share per source
│   ├── ECharts Bar              data: transaction_analysis.by_source → volume per source
│   └── Stat                     high_risk_transactions → KpiCard ("High-risk tx")
├── Section: Admin Activity        (report_sections.admin_activity)
│   ├── KpiCardRow                total_admin_actions · unique_admins · failed_actions
│   ├── ECharts Bar               by_action_type → count per action type
│   └── ECharts Pie (donut)       by_risk_level  → risk distribution (low/medium/high)
├── Section: Suspicious Activity   (report_sections.suspicious_activity)
│   └── KpiCardRow (4 cards)      large_transactions · off_hours_transactions ·
│                                 high_frequency_users · round_number_transactions
│       (optionally: ECharts Bar  — the 4 counts side by side)
├── Section: Risk Assessment       (report_sections.risk_assessment)
│   ├── ECharts Gauge             overall_risk_score → 0–100 dial (zones: 0–49 green, 50–69 amber, 70+ red)
│   ├── Table                     risk_factors → rows per category, columns: category ·
│   │                             counts · percentages · risk_level (LevelBadge)
│   │                             (optionally: ECharts Radar — the 4 percentages, one axis per category)
│   └── AlertList                 recommendations[] → Alert per item, tone from priority
│                                 (critical red / high amber / medium blue / low neutral)
└── Section: Daily Breakdown       (report_sections.daily_breakdown, daily type only)
    ├── ECharts Line (2 series)   x = Jalali date; series = total_volume, unique_users
    └── DataTable                 per day: total_transactions · total_volume · unique_users · admin_initiated
```

Chart type summary for this endpoint:

| Data block | ECharts type | Why |
|---|---|---|
| by_type / by_source count share | `pie` (donut) | distribution of parts of a whole |
| by_type / by_source volume | `bar` | compare magnitudes across categories |
| by_action_type | `bar` | discrete category counts |
| by_risk_level | `pie` | share of risk buckets |
| suspicious counts | `bar` (or KPI cards) | 4 fixed categories comparison |
| overall_risk_score | `gauge` | single 0–100 value, zone coloring |
| risk_factors percentages | `radar` (optional) | multi-dimension profile |
| daily_breakdown trend | `line` | time series over dates |

### 5.4 Suspicious activity (`POST audit/suspicious-activity`)

```
Page: Suspicious Activity
├── CriteriaBanner (chips)        detection_criteria → "Large ≥ 50,000,000" · "Frequency ≥ 10/day"
│                                 + detection_period.from → to
├── SummaryRow
│   ├── KpiCard      total_suspicious_activities
│   ├── KpiCard      unique_users_involved
│   └── TagGroup     summary.by_type → one colored Tag per category
├── Tabs — one tab per present collection (skip null collections)
│   ├── Tab: Rapid Succession        rapid_succession
│   │   └── DataTable  columns: user_name · amount · type · created_at · flags (Tag) · pattern (tooltip)
│   ├── Tab: Unusual Admin Activity  unusual_admin_activity
│   │   └── DataTable  same columns, highlight "admin initiated" rows
│   ├── Tab: Large Transactions      large_transactions
│   │   └── DataTable  + optional ECharts Bar — top amounts per user
│   ├── Tab: Off-Hours               off_hours_transactions
│   │   └── DataTable  + optional ECharts Bar — hour histogram (from item.hour)
│   ├── Tab: High-Frequency Users    high_frequency_users   (AGGREGATED rows)
│   │   ├── DataTable  columns: user_name · activity date · transaction_count ·
│   │   │             total_volume · avg_transaction_amount · first_transaction · last_transaction
│   │   └── ECharts Bar              total_volume per user (or per user+date)
│   └── Tab: Round Numbers           round_number_patterns
│       └── DataTable  same columns as transaction tables
└── EmptyState per tab when collection is an empty array
```

Shared render rules for item tables:
- `flags` → `JSON.parse()` → render as **Tag/Badge** per flag value (`rapid_succession`,
  `unusual_admin_activity`, `large_amount`, `off_hours`, `high_frequency`, `round_numbers`).
- `admin_initiated === "true"` → mark row with a "admin" icon/tag.
- `hour` → integer label, useful for an hour-of-day histogram (Bar, x = 0–23).

Chart type summary for this endpoint:

| Data block | ECharts type | Why |
|---|---|---|
| summary.by_type | `pie` or TagGroup | category distribution |
| large_transactions amounts | `bar` | compare magnitudes |
| off_hours by hour | `bar` | histogram of `item.hour` |
| high_frequency total_volume | `bar` | compare per-user volume |

---

## 6. Admin Page UI Mockups

Low-fidelity wireframes. Legend: `[low]/[med]/[high]` = risk level badges
(green/amber/red), `▮▯●◆` = chart glyphs, `◆` = admin-initiated marker.

### 6.1 Admin Actions — List page

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Admin · Audit Logs                                             [Search…] ⏎  │
├──────────────────────────────────────────────────────────────────────────────┤
│  Filters:  Admin [All ▾]  Action [All ▾]  Risk [All ▾]  Method [All ▾]       │
│            From [1404-06-01]  To [1404-06-30]                     [Reset]    │
├──────────────────────────────────────────────────────────────────────────────┤
│  Action                         Admin     Resource      Method Status Risk   │
├──────────────────────────────────────────────────────────────────────────────┤
│  ▶ Create Wallet #12            Sara M.   Wallet        POST     201  [med]  │
│  ▶ Update User #8               Ali R.    User          PUT      200  [low]  │
│  ▶ Delete DigitalAsset #3       Omid K.   DigitalAsset  DELETE   204  [high] │
│  ▶ Login                        Sara M.   —             POST     200  [low]  │
│  ▶ Create Wallet #9 (failed)    Mina A.   Wallet        POST     422  [med]  │
├──────────────────────────────────────────────────────────────────────────────┤
│  Showing 1–15 of 342                                   ‹ 1 2 3 … 23 ›        │
└──────────────────────────────────────────────────────────────────────────────┘

  - Click a row / ▶ → opens detail drawer (mockup 6.2)
  - Risk column: green/amber/red badge; Status ≥400 shown with ⚠
```

### 6.2 Admin Actions — Detail drawer

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  ×  Create Wallet #12                                           [high]  ✓   │
│     1404-06-30 14:03:22  ·  by Sara Mohammadi                                 │
├──────────────────────────────────────────────────────────────────────────────┤
│  Admin          Sara Mohammadi  <sara@example.com>                            │
│  Route          api.v1.admin.wallet.create                          [copy]   │
│  Method/Status  [POST]  [201]                                                 │
│  IP             192.168.1.45         Session  ctUy…Ndwi0          [copy]      │
│  User-Agent     bruno-runtime/2.10.1                                          │
├──────────────────────────────────────────────────────────────────────────────┤
│   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐                     │
│   │ Exec     │  │ Memory   │  │ Req Size │  │ Resp Size│                     │
│   │ 59.8 ms  │  │ 4.0 MB   │  │  774 B   │  │  312 B   │                     │
│   └──────────┘  └──────────┘  └──────────┘  └──────────┘                     │
├──────────────────────────────────────────────────────────────────────────────┤
│  ▸ Request Data (JSON, sanitized — passwords/tokens redacted)                 │
│  {                                                                             │
│    "wallet_id": 12,                                                            │
│    "amount": 50000000,                                                         │
│    "description": "credit from deposit"                                        │
│  }                                                                             │
├──────────────────────────────────────────────────────────────────────────────┤
│  ▸ Resource snapshot — Wallet #12                                              │
│  { "id": 12, "balance": 128400000, "status": "active" }                        │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 6.3 Compliance Report page

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Compliance Report          1404-03-01 → 1404-03-31               [daily ▾]  │
├──────────────────────────────────────────────────────────────────────────────┤
│  ┌───────────┐ ┌───────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐         │
│  │ Tx Total  │ │ Net Volume│ │ Users    │ │ Credits  │ │ Debits   │         │
│  │  1,284    │ │ +2.1B ریال│ │   96     │ │  812     │ │  472     │         │
│  └───────────┘ └───────────┘ └──────────┘ └──────────┘ └──────────┘         │
│  ┌───────────┐ ┌───────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐         │
│  │ Credits   │ │ Debits    │ │ Avg/Tx   │ │ Large    │ │ High-Risk│         │
│  │ Vol 2.1B  │ │ Vol 1.1B  │ │ 2.49M    │ │ 57 (≥5M) │ │   12     │         │
│  └───────────┘ └───────────┘ └──────────┘ └──────────┘ └──────────┘         │
├──────────────────────────────────────────────────────────────────────────────┤
│  Transaction Analysis                                                         │
│   ┌───────────────┐  ┌───────────────┐                                        │
│   │ ● deposit 62% │  │ ▇ deposit ▇▇  │                                        │
│   │ ● withdraw 38%│  │ ▇ withdraw ▇  │                                        │
│   │    Pie/Donut  │  │  Bar (volume) │                                        │
│   └───────────────┘  └───────────────┘                                        │
├──────────────────────────────────────────────────────────────────────────────┤
│  Admin Activity                        Suspicious Activity                     │
│  ▇ create ▇▇ update ▇ delete           [57 large] [214 off-hrs] [6 HF] [33 rnd]│
│  ● low ● med ● high        (Pie)       (4 KPI chips / optional Bar)           │
├──────────────────────────────────────────────────────────────────────────────┤
│  Risk Assessment                                                               │
│   ┌────────┐   Risk factors (table or Radar):                                  │
│   │  62 /  │   TransactionVolume  [med]  high-amt 8 (15.3%)                   │
│   │  100   │   Temporal           [high] off-hrs 42 (32.8%)                    │
│   │ Gauge  │   Pattern            [low]  round 6 (4.7%)                        │
│   └────────┘   AdminActivity      [med]  high-risk 3 (6.1%)                    │
│   !  High · Enhanced monitoring recommended         (Alert, tone = priority)   │
├──────────────────────────────────────────────────────────────────────────────┤
│  Daily Breakdown (daily only)                                                  │
│   ◢────────────── volume ─────────────── users ─────────►   (Line chart)      │
│   ┌────────────┬──────┬────────┬───────┬────────┐                              │
│   │ Date       │ Tx   │ Volume │ Users │ Admin  │                              │
│   │ 1404-03-01 │ 41   │ 98.2M  │   8   │   2    │                              │
│   │ 1404-03-02 │ 63   │ 155.7M │  12   │   5    │                              │
│   └────────────┴──────┴────────┴───────┴────────┘                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

### 6.4 Suspicious Activity page

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  Suspicious Activity       1404-03-01 → 1404-03-31                           │
│  Criteria:  [Large ≥ 50M]  [Freq ≥ 10/day]  [Off-hours]  [Round #]           │
├──────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌───────────────────────────────────┐       │
│  │ Suspicious  │ │ Users       │ │ by_type: [large] [off-hrs]        │       │
│  │     47      │ │     12      │ │           [freq] [round] [rapid]  │       │
│  └─────────────┘ └─────────────┘ └───────────────────────────────────┘       │
├──────────────────────────────────────────────────────────────────────────────┤
│  Tabs:  [ Rapid Succession ] [ Admin Activity ] [ Large Tx ] [ Off-Hours ]   │
│         [ High-Freq Users ]  [ Round Numbers ]                               │
├──────────────────────────────────────────────────────────────────────────────┤
│  User          Amount       Type      Time       Flags                       │
│  Reza T.       15,000,000   deposit   03:12:04   [off_hours] [rapid]        │
│  Mina A.       12,000,000   deposit   03:14:55   [off_hours] [rapid]        │
│  ◆ Sara M.     21,000,000   deposit   11:02:30   [unusual_admin_activity]   │
│                                                                              │
│  (optional) Hour histogram: ▇▁▇▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▁▂   (Bar, x=0–23)       │
├──────────────────────────────────────────────────────────────────────────────┤
│  High-Frequency Users — daily aggregate (transaction_id = 0 rows)            │
│  User      Date        Tx #   Volume    Avg       First → Last               │
│  Sara M.   1404-03-12  18     512.3M    28.5M     09:02 → 22:40             │
│  Ali R.    1404-03-15  11     305.0M    27.7M     10:11 → 19:58             │
│                                                                              │
│  (optional) Bar chart: volume per user                                       │
└──────────────────────────────────────────────────────────────────────────────┘

  - Tabs whose collection is null are hidden; empty array → EmptyState in tab
  - ◆ marks admin_initiated = "true" rows
```

---

## Error responses (all endpoints)

- `401 Unauthenticated` — missing/invalid staff token.
- `403 Forbidden` — token valid but role lacks the permission.
- `422 Unprocessable Entity` — validation failure; `errors` keyed by field, e.g.
  `date_from`, `user_ids.0`. Always show these server-side messages.
- `404 Not Found` — log id doesn't exist (`admin-actions.show`).

## Quick reference

| Endpoint | Method | Permission |
|---|---|---|
| `/audit/admin-actions` | GET | `audits.admin_actions_view` |
| `/audit/admin-actions/{id}` | GET | `audits.admin_actions_view` |
| `/audit/compliance-report` | POST | `audits.compliance_reports_view` |
| `/audit/suspicious-activity` | POST | `audits.suspicious_activity_view` |
