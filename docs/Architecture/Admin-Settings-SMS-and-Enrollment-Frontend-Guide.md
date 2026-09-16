# Admin Settings — SMS & Enrollment Providers (Frontend Guide)

> **Status: not implemented.** This is the HTTP contract the admin frontend can build against for three settings areas that do not exist on the backend yet. It covers only what the frontend sends and receives — no server internals.
>
> Follows the same `list → schema → settings → PUT` pattern as the existing payment gateway settings endpoints, so the same generic settings form renderer can be reused.

## Contents

| Area | Endpoints |
| --- | --- |
| [SMS gateways](#2-sms-gateways) | `GET/PUT /api/v1/admin/settings/sms-gateways`, `GET .../sms-gateways/{gateway}` |
| [SMS notifications](#3-sms-notifications) | `GET/PUT /api/v1/admin/settings/sms-notifications` |
| [Enrollment providers](#4-enrollment-providers) | `GET/PUT /api/v1/admin/settings/enrollment-providers`, `GET .../enrollment-providers/{provider}` |

---

## 1. Shared conventions

- **Base URL:** `{APP_URL}/api/v1/admin`
- **Auth:** the normal authenticated admin session, identical to the rest of the admin API. No new permission or login flow.
- **Permissions:** `settings.view_any` is required for every `GET`, `settings.update` for every `PUT`. Missing permission → `403`.
- **Content type:** `application/json` for all request bodies. No `multipart/form-data` is needed — none of these settings carry media/icon fields.
- **Envelope:** every response is:
  - success: `{ "message": string, "data": <payload>, "metadata": [] }`
  - validation failure (`422`): `{ "message": string, "errors": { "<field>": [string] }, "metadata": [] }`
  - forbidden (`403`): `{ "message": "This action is forbidden.", "data": {}, "metadata": [] }`
- **`schema` + `settings` pair:** each item is `{ key, label, schema, settings }`. `schema` is the presentation contract (groups of fields), `settings` is the current value. **Render the form from `schema`, never from `settings`.**
- **Field descriptor:**
  ```ts
  interface FieldSchema {
    key: string;                 // exact request-body path, e.g. "api_key"
    type: 'text' | 'password' | 'textarea' | 'url' | 'boolean' | 'number';
    label: string;               // Persian, localized server-side
    required: boolean;
    sensitive?: boolean;         // value is masked on read-back
    default?: string | number | boolean | null;
  }
  type Schema = Record<string, FieldSchema[]>; // group name -> fields
  ```
- **Secret handling:**
  - reads return the masked placeholder `***REDACTED***`, never a real secret;
  - on `PUT`, **omit** the field or send `null` to keep the stored secret; sending `***REDACTED***` back verbatim also keeps it;
  - only a real new string overwrites it; `""` clears it.
- **`settings` is always an object** — when nothing has been saved yet the API returns the effective defaults, so the form always has values to bind. Never key off `settings == []`.
- **Unknown keys are ignored** (not persisted, no error). Send only documented keys. The single exception: an unrecognized **option key** inside the `options` map of [section 3](#3-sms-notifications) is rejected with `422`, because silently dropping a notification toggle would be a configuration bug.

---

## 2. SMS gateways

Configure the SMS provider(s) used for transactional SMS. Only **IPPanel** exists today, but the payload is a list, so a future gateway appears as another item with no frontend change.

### 2.1 Endpoints

| Purpose | Method | Endpoint |
| --- | --- | --- |
| List gateways with schema | `GET` | `/api/v1/admin/settings/sms-gateways` |
| Read one gateway | `GET` | `/api/v1/admin/settings/sms-gateways/{gateway}` |
| Save one gateway | `PUT` | `/api/v1/admin/settings/sms-gateways/{gateway}` |

`{gateway}` is the gateway key. Currently: `ippanel`. Unknown key → `404`.

### 2.2 Fields — `ippanel`

| Group | `key` | Type | Required | Sensitive | Notes |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | Gateway is active and may be used for sending. |
| `general` | `label` | text | yes | no | Display name in the admin panel. |
| `general` | `from` | text | yes | no | Registered sender number. |
| `credentials` | `api_key` | password | yes | **yes** | SMS provider API key. |
| `testing` | `sandbox` | boolean | no | no | Default `false`. When true, sends are simulated (logged as `Sandbox_*`, no real SMS). |

### 2.3 Response — `GET /sms-gateways`

```json
{
  "message": "عملیات با موفقیت انجام شد.",
  "data": [
    {
      "key": "ippanel",
      "label": "آی‌پی‌پنل",
      "schema": {
        "general": [
          { "key": "enabled", "type": "boolean", "label": "فعال", "required": true },
          { "key": "label", "type": "text", "label": "عنوان", "required": true },
          { "key": "from", "type": "text", "label": "شماره فرستنده", "required": true }
        ],
        "credentials": [
          { "key": "api_key", "type": "password", "label": "کلید API", "required": true, "sensitive": true }
        ],
        "testing": [
          { "key": "sandbox", "type": "boolean", "label": "حالت آزمایشی", "required": false, "default": false }
        ]
      },
      "settings": {
        "enabled": true,
        "label": "آی‌پی‌پنل",
        "from": "1000",
        "api_key": "***REDACTED***",
        "sandbox": false
      }
    }
  ],
  "metadata": []
}
```

`GET /sms-gateways/{gateway}` returns **one item shaped exactly like an element of `data`** (`{ key, label, schema, settings }`), not wrapped in an array — so a detail page can be opened by deep link without first fetching the list.

### 2.4 Request — `PUT /sms-gateways/{gateway}`

```json
{
  "enabled": true,
  "label": "آی‌پی‌پنل",
  "from": "1000",
  "api_key": "real-api-key",
  "sandbox": false
}
```

- `enabled`, `label`, `from` required; `sandbox` optional (defaults `false`); `api_key` is nullable to allow "keep current secret", but turning the gateway on without a stored key is rejected.
- `api_key: null` or omitted → keep the stored key. `api_key: ""` → clears it.
- Response: `200` with the saved gateway object, `api_key` masked again.
- `422` when the gateway would be left enabled with an incomplete configuration, with the offending field in `errors`.

### 2.5 Validation rules

| Rule | Status |
| --- | --- |
| `enabled = true` and (`from` empty or no stored `api_key`) | `422` on `from` / `api_key` |
| `label` empty | `422` |
| `sandbox` not a boolean | `422` |

> **Caveat:** do not present `enabled` as a hard "no SMS will be sent" guarantee. It is a configuration switch, and enforcement on the sending side may land after this screen.

---

## 3. SMS notifications

One row per SMS notification type, each with **`enabled`** and **`pattern_code`**.

### 3.1 Endpoints

| Purpose | Method | Endpoint |
| --- | --- | --- |
| Read all notification options | `GET` | `/api/v1/admin/settings/sms-notifications` |
| Save notification options | `PUT` | `/api/v1/admin/settings/sms-notifications` |

### 3.2 Options

`wired` tells you whether the option is live — it is a **status signal for the UI/QA, not a form control**. Options with `wired: false` can be configured and saved, but no SMS is sent for them yet.

| `key` | Label (fa) | `log_type` | `wired` | Notes |
| --- | --- | --- | --- | --- |
| `otp` | کد ورود (OTP) | `OTP` | yes | Login code. |
| `refund_completed` | تأیید استرداد وجه | `REFUND` | yes | Free-text SMS today; a pattern is optional until one is defined. |
| `order_paid` | پرداخت موفق سفارش | `ORDER` | no | Planned. |
| `enrollment_ready` | آماده‌سازی دسترسی آموزشی | `ENROLLMENT` | no | Planned. |
| `wallet_campaign_credited` | واریز هدیه/کمپین کیف پول | `WALLET` | no | Planned. |

The option list is returned by the API (not hardcoded in the frontend), so a new option appears automatically.

### 3.3 Response — `GET /sms-notifications`

```json
{
  "message": "عملیات با موفقیت انجام شد.",
  "data": {
    "schema": {
      "enabled": { "key": "enabled", "type": "boolean", "label": "فعال", "required": true },
      "pattern_code": { "key": "pattern_code", "type": "text", "label": "کد الگو", "required": false }
    },
    "options": [
      {
        "key": "otp",
        "label": "کد ورود (OTP)",
        "log_type": "OTP",
        "wired": true,
        "settings": { "enabled": true, "pattern_code": "mdoe1j1587" }
      },
      {
        "key": "refund_completed",
        "label": "تأیید استرداد وجه",
        "log_type": "REFUND",
        "wired": true,
        "settings": { "enabled": false, "pattern_code": "" }
      },
      {
        "key": "order_paid",
        "label": "پرداخت موفق سفارش",
        "log_type": "ORDER",
        "wired": false,
        "settings": { "enabled": false, "pattern_code": "" }
      }
    ]
  },
  "metadata": []
}
```

- Each option always returns an **effective value** — the saved admin value when one exists, otherwise the server default. There is no "source"/"is_default" field; the frontend neither receives nor needs to know which one it got.
- `pattern_code` is `""` (never `null`) when unset, so a text input binds cleanly.
- `schema` is a flat map of the two per-option fields rather than grouped arrays, because every option has the same two fields.
- The example is abridged: the server returns all five options from section 3.2, in that order.

### 3.4 Request — `PUT /sms-notifications`

One save for the whole section (single Save button):

```json
{
  "options": {
    "otp": { "enabled": true, "pattern_code": "mdoe1j1587" },
    "refund_completed": { "enabled": true, "pattern_code": "abc1234567" }
  }
}
```

Semantics:

- `options` is required and keyed by option key; unknown keys → `422`.
- **Omitted option key → unchanged.** This is a merge, not a replace, so a partial save cannot silently disable the rest of the options.
- **Inside a provided option, both `enabled` and `pattern_code` are required** (`422` if either is missing) — the form always submits both.
- `pattern_code` may be empty (`""`) or `null`; when empty, the sender falls back to its free-text/custom template.
- Disabling an option never clears its `pattern_code`; the stored code is kept so re-enabling restores it.
- Response: `200` with the full `GET` payload, so the frontend re-renders from server state in one round-trip.

---

## 4. Enrollment providers

Configure the external providers that enrollments and live sessions are delivered through. Same interaction model as payment gateways.

### 4.1 Endpoints

| Purpose | Method | Endpoint |
| --- | --- | --- |
| List providers with schema | `GET` | `/api/v1/admin/settings/enrollment-providers` |
| Read one provider | `GET` | `/api/v1/admin/settings/enrollment-providers/{provider}` |
| Save one provider | `PUT` | `/api/v1/admin/settings/enrollment-providers/{provider}` |

Unknown `{provider}` → `404`.

| `{provider}` | Label | What it is |
| --- | --- | --- |
| `ims` | IMS | Student/course management system |
| `moodle` | مودل | LMS (courses, enrollments, student login) |
| `spotplayer` | اسپات‌پلیر | Video/recording delivery |
| `skyroom` | اسکای‌روم | Live sessions |
| `niliroom` | نیلی‌روم | Live-session panel — the only panel behind BBB live sessions (ADR 0013) |

`moodle_quiz` is **not** a configurable provider — it has no credentials of its own and is served by the Moodle configuration. `GET/PUT .../enrollment-providers/moodle_quiz` returns `404`; do not render a form for it.

### 4.2 Fields per provider

Each provider returns its own `schema` groups. The `Default` column is the value shown when nothing has been saved; "server default" means a value supplied by the deployment configuration.

#### `ims`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | yes | no | server default |
| `credentials` | `api_key` | password | yes | **yes** | server default |
| `advanced` | `timeout` | number | no | no | `15` |

#### `moodle`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | yes | no | server default |
| `credentials` | `token` | password | yes | **yes** | server default |
| `credentials` | `auth_userkey_token` | password | yes | **yes** | server default |
| `advanced` | `default_role_id` | number | no | no | `5` |
| `advanced` | `default_login_redirect_script` | text | no | no | `/my/` |
| `advanced` | `timeout` | number | no | no | `15` |

`auth_userkey_token` is what lets a customer log in to Moodle from the shop, so it is required alongside `token`.

#### `spotplayer`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `endpoint` | url | yes | no | `https://panel.spotplayer.ir/license/edit/` |
| `credentials` | `api_key` | password | yes | **yes** | server default |
| `testing` | `sandbox` | boolean | no | no | `false` |
| `advanced` | `timeout` | number | no | no | `15` |

Note the key is `endpoint`, not `base_url`.

#### `skyroom`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | no | no | `https://www.skyroom.online/skyroom/api` |
| `credentials` | `api_key` | password | yes | **yes** | server default |

#### `niliroom`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | yes | no | server default |
| `credentials` | `api_token` | password | yes | **yes** | server default |

### 4.3 Response — `GET /enrollment-providers`

```json
{
  "message": "عملیات با موفقیت انجام شد.",
  "data": [
    {
      "key": "ims",
      "label": "IMS",
      "state": { "enabled": false, "configured": false, "ready": false },
      "schema": {
        "general": [
          { "key": "enabled", "type": "boolean", "label": "فعال", "required": true, "default": false }
        ],
        "connection": [
          { "key": "base_url", "type": "url", "label": "آدرس سرویس", "required": true }
        ],
        "credentials": [
          { "key": "api_key", "type": "password", "label": "کلید API", "required": true, "sensitive": true }
        ],
        "advanced": [
          { "key": "timeout", "type": "number", "label": "مهلت (ثانیه)", "required": false, "default": 15 }
        ]
      },
      "settings": {
        "enabled": false,
        "base_url": "https://ims.example.ir",
        "api_key": "***REDACTED***",
        "timeout": 15
      }
    }
  ],
  "metadata": []
}
```

`state` is read-only and computed by the server (the response lists all six providers; only `ims` is shown above):

| Field | Meaning |
| --- | --- |
| `enabled` | The admin's switch. A provider that a delivery option requires but that is switched off is reported as `disabled` on the affected enrollment and needs an admin action — it is never silently skipped. |
| `configured` | All required fields are filled. |
| `ready` | `enabled && configured` — the badge to display. Disabled-but-configured is a normal state; enabled-but-unconfigured is a configuration error. |

`GET /enrollment-providers/{provider}` returns **one item shaped exactly like an element of `data`** (`{ key, label, state, schema, settings }`), not wrapped in an array.

### 4.4 Request — `PUT /enrollment-providers/{provider}`

Flat body, exactly the provider's keys:

```json
{
  "enabled": true,
  "base_url": "https://ims.example.ir",
  "api_key": "real-api-key",
  "timeout": 15
}
```

- `enabled` is always required; the provider's connection/credential fields are required when `enabled = true`. Saving a disabled provider with empty connection fields is allowed, so an admin can stage configuration before switching it on. Failures are `422` with the field name(s) in `errors`.
- Sensitive fields follow the shared rule: omitted or `null` keeps the stored secret, `***REDACTED***` keeps it too, `""` clears it.
- **Full replace semantics** for non-sensitive fields: any documented non-sensitive key that is omitted is reset to its default. Send the whole object from the form.
- Response: `200` with the saved provider object including the recomputed `state`.

> **Why is this body flat while payment gateways nest provider fields under `config`?** Payment gateways also carry shared display fields (`label`, `description`, `icon`). Enrollment providers have no shared display fields, so there is nothing to hoist. The generic renderer is unaffected: it renders groups from `schema` and submits the `settings` keys as-is.

---

## 5. Frontend rendering rules (all three areas)

1. `GET` the list (or the single resource), then build the form from `schema` group by group, in the order the groups and fields are returned. Do not hardcode field lists, labels, or gateway/provider lists.
2. Bind inputs to `settings` using the field `key` as the literal request path.
3. For `type: "password"` with `sensitive: true`, show the masked placeholder as a hint, not as a value. Submit `null` when the admin has not typed a new secret.
4. `PUT` the whole form; re-render from the response instead of assuming success locally — the server is the source of truth for masked values and `state`.
5. Surface `errors` keyed by field path on `422`, and a permission error on `403`.
6. Never attempt to display a decrypted secret; the API never returns one.
7. Use `wired` (SMS notifications) and `state` (providers) to show status, and keep status badges visually distinct from the editable controls.
