# Admin Settings — SMS and Enrollment Provider API Contract

> **Status: not implemented.** This document is the agreed contract for three admin settings areas that still need backend endpoints. Nothing here exists in the codebase yet; it is written so the admin frontend can be built against it and the backend can be implemented to match.
>
> Modeled on the implemented `App\Http\Controllers\Api\Admin\Settings\PaymentGatewaySettingsController` (`GET/GET {key}/PUT {key}`) and `GatewaySettingCreateData::schemaForGateway()`. Where this contract deliberately differs from that controller, the difference is called out.

## Contents

| Area | Endpoints |
| --- | --- |
| [SMS gateways](#2-sms-gateways) | `GET/PUT /api/v1/admin/settings/sms-gateways`, `GET .../sms-gateways/{gateway}` |
| [SMS notifications](#3-sms-notifications) | `GET/PUT /api/v1/admin/settings/sms-notifications` |
| [Enrollment providers](#4-enrollment-providers) | `GET/PUT /api/v1/admin/settings/enrollment-providers`, `GET .../enrollment-providers/{provider}` |

---

## 1. Shared conventions

Everything below follows the existing admin settings API.

- **Base URL:** `{APP_URL}/api/v1/admin`
- **Auth:** the `staff` guard, same middleware chain as the payment gateway settings page (`auth.cookie:staff`, `auth:staff`, `admin.audit`). No new authentication or permission is introduced.
- **Permissions:** `settings.view_any` for every `GET`, `settings.update` for every `PUT` (existing `PermissionEnum` values, enforced through `Gate::authorize('viewAny'|'update', Setting::class)`). Missing permission → `403`.
- **Content type:** `application/json` for all request bodies. No `multipart/form-data` is needed — none of these settings carry media/icon fields.
- **Envelope:** every response goes through `apiResponse()`:
  - success: `{ "message": string, "data": <payload>, "metadata": [] }`
  - validation failure (`422`): `{ "message": string, "errors": { "<field>": [string] }, "metadata": [] }`
  - forbidden (`403`): `{ "message": "This action is forbidden.", "data": {}, "metadata": [] }`
- **`schema` + `settings` pair:** each item is `{ key, label, schema, settings }` where `schema` is a pure presentation contract (groups of fields) and `settings` is the current value. Render the form from `schema`, never from `settings`.
- **Field descriptor:**
  ```ts
  interface FieldSchema {
    key: string;                 // exact request-body path, e.g. "config.api_key"
    type: 'text' | 'password' | 'textarea' | 'url' | 'boolean' | 'number';
    label: string;               // Persian, localized server-side
    required: boolean;
    sensitive?: boolean;         // value is masked on read-back
    default?: string | number | boolean | null;
  }
  type Schema = Record<string, FieldSchema[]>; // group name -> fields
  ```
- **Secret handling** (identical to the integration settings today):
  - secrets are encrypted at rest;
  - reads return the masked placeholder `***REDACTED***` (not a real value);
  - on `PUT`, **omit** the field or send `null` to keep the stored secret unchanged; sending `***REDACTED***` back verbatim also keeps it unchanged. Only a real new string overwrites it.
- **`settings` is always an object.** Unlike `payment-gateways` (which returns `[]` when nothing is stored), these endpoints return **config-derived defaults** when no DB row exists, so the form always has values to bind. Never key off `settings == []`.
- **Unknown keys are ignored**, not persisted, and do not fail the request. Send only documented keys. The single exception: an unrecognized **option key** inside the `options` map of [section 3](#3-sms-notifications) is rejected with `422`, because silently dropping a notification toggle would be a configuration bug.

---

## 2. SMS gateways

Purpose: configure the SMS provider(s) used to send transactional SMS. Only **IPPanel** exists today, but the list shape is future-proof so adding a second gateway is a backend-only change (new enum case + DTO + config entry) and the frontend renders it automatically.

### 2.1 Endpoints

| Purpose | Method | Endpoint |
| --- | --- | --- |
| List gateways with schema | `GET` | `/api/v1/admin/settings/sms-gateways` |
| Read one gateway | `GET` | `/api/v1/admin/settings/sms-gateways/{gateway}` |
| Save one gateway | `PUT` | `/api/v1/admin/settings/sms-gateways/{gateway}` |

`{gateway}` is the gateway key. Currently: `ippanel`. Unknown key → `404`.

### 2.2 Fields — `ippanel`

Derived from `App\Services\IpPanelSmsService` and `config('sms.gateways.ippanel')`.

| Group | `key` | Type | Required | Sensitive | Notes |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | Gateway is active and may be used for sending. |
| `general` | `label` | text | yes | no | Display name in the admin panel. |
| `general` | `from` | text | yes | no | Sender number registered with IPPanel (`$this->from`). |
| `credentials` | `api_key` | password | yes | **yes** | IPPanel API key. Empty/missing config blocks sending and records the attempt as skipped (`SmsLog::STATUS_SKIPPED`, reason `not_configured`). |
| `testing` | `sandbox` | boolean | no | no | Default `false`. When true, sends are logged as `Sandbox_*` and no HTTP call is made. |

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

`GET /sms-gateways/{gateway}` returns **one item shaped exactly like an element of `data`** (`{ key, label, schema, settings }`), not wrapped in an array. This intentionally differs from `payment-gateways/{gateway}`, which returns only the raw settings object — include the schema so a detail page can be opened by deep link without first fetching the list.

### 2.4 Request — `PUT /sms-gateways/{gateway}`

Flat body; the same keys as `schema` (no nested `config` object — there are no shared display fields here):

```json
{
  "enabled": true,
  "label": "آی‌پی‌پنل",
  "from": "1000",
  "api_key": "real-api-key",
  "sandbox": false
}
```

- `enabled`, `label`, `from` required; `sandbox` optional (defaults `false`); `api_key` is nullable to allow "keep current secret", but a save that turns the gateway on without a stored key is rejected (see 2.5).
- `api_key: null` or omitted → keep the stored key. `api_key: ""` → stored as empty (this is how an admin clears a key).
- Response: `200` with the saved gateway object, `api_key` masked again.
- `422` when the gateway would be left enabled with an incomplete configuration, with the offending field in `errors` — see 2.5.

### 2.5 Validation rules

| Rule | Failing status |
| --- | --- |
| `enabled = true` and (`from` empty or no stored `api_key`) | `422` on `from` / `api_key` |
| `label` empty | `422` |
| `sandbox` not a boolean | `422` |

### 2.6 Backend storage and fallback (implementation note)

- Setting key: `SettingKeyEnum::SMS_IPPANEL = 'sms.ippanel'`, group `sms`, `type` `json` — mirrors `payment.mellat`.
- `SmsGatewaySettingData::secretFields()` → `['api_key']`, so it is encrypted on write (`SettingsService::set`) and decrypted on read (`SettingsService::get`).
- Read precedence: stored `sms.ippanel` row → `config('sms.gateways.ippanel')`, the one rule in `SmsGatewayEnum::resolvedSettings()`. `IpPanelSmsService` resolves the same pair at send time, so a saved key takes effect on the next send and the gateway `enabled` flag is a real kill switch.
- The config entry supplies the default `label`, so a never-saved gateway still renders with a title instead of an empty field.

---

## 3. SMS notifications

Purpose: one section per SMS notification type, each with **`enabled`** and **`pattern_code`**. Defaults come from a config file; admin edits are persisted to the settings table and override the config (same precedence as `AbstractIntegrationService::resolveConfig`).

### 3.1 Endpoints

| Purpose | Method | Endpoint |
| --- | --- | --- |
| Read all notification options | `GET` | `/api/v1/admin/settings/sms-notifications` |
| Save notification options | `PUT` | `/api/v1/admin/settings/sms-notifications` |

### 3.2 Options

`wired` tells you whether any code currently reads the option — it is a **contract signal for the frontend/QA**, not a form control. Options that are not wired are stored and returned, but no SMS is sent for them yet.

| `key` | Label (fa) | `log_type` | `wired` | Notes |
| --- | --- | --- | --- | --- |
| `otp` | کد ورود (OTP) | `OTP` | yes | `OtpSmsNotification` reads the option's `pattern_code`; an enabled option with an empty code skips the send and records it. |
| `refund_completed` | تأیید استرداد وجه | `REFUND` | yes (free text) | `RefundCompletedNotification` honours a configured `pattern_code` when present and sends its free-text message otherwise. |
| `order_paid` | پرداخت موفق سفارش | `ORDER` | no | Planned. |
| `enrollment_ready` | آماده‌سازی دسترسی آموزشی | `ENROLLMENT` | no | Planned. |
| `wallet_campaign_credited` | واریز هدیه/کمپین کیف پول | `WALLET` | no | Planned. |

The option list itself is served by the API (not hardcoded in the frontend) so adding an option is a backend-only change.

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

- Every option always returns an **effective value**: the saved admin value when one exists, otherwise the configured default. There is no per-option "source" field — the frontend cannot and need not tell which one it got.
- `pattern_code` is `""` (never `null`) when unset, so a text input binds cleanly.
- `schema` is a flat map of the two per-option fields rather than grouped arrays, because every option has the same two fields.
- The example is abridged: the server returns all five options from section 3.2, in that order.

### 3.4 Request — `PUT /sms-notifications`

One save for the whole section (the form has a single Save button):

```json
{
  "options": {
    "otp": { "enabled": true, "pattern_code": "mdoe1j1587" },
    "refund_completed": { "enabled": true, "pattern_code": "abc1234567" }
  }
}
```

Semantics:

- `options` is required and must be an object keyed by a known option key; unknown keys → `422`.
- **Omitted option key → unchanged.** This is a merge, not a replace, so a partial save cannot silently disable the rest of the options.
- **Inside a provided option, both `enabled` and `pattern_code` are required** (`422` if either is missing) — a form always submits both.
- `pattern_code` may be empty (`""`) or `null`. An option that requires a pattern (every option except `refund_completed`) **skips the send and records it** when the code is empty — there is no fallback to custom text, because the provider filters it. `refund_completed` does not require a pattern and sends its free-text message when no code is configured.
- Disabling an option never clears its `pattern_code`; the stored code is kept so re-enabling restores it.
- Response: `200` with the full `GET` payload, so the frontend can re-render from the server state in one round-trip.

### 3.5 Config file (defaults)

Notification defaults live in a new `config/sms.php`:

```php
return [
    'gateways' => [
        'ippanel' => [
            'enabled' => (bool) env('SMS_IPPANEL_ENABLED', true),
            'label'   => 'آی‌پی‌پنل',
            'from'    => env('IPPANEL_FROM', '1000'),
            'api_key' => env('IPPANEL_API_KEY'),
            'sandbox' => (bool) env('IPPANEL_SANDBOX', false),
        ],
    ],

    'notifications' => [
        'otp' => [
            'enabled'      => (bool) env('SMS_OTP_ENABLED', true),
            'pattern_code' => (string) env('SMS_OTP_PATTERN', 'mdoe1j1587'),
        ],
        'refund_completed' => [
            'enabled'      => (bool) env('SMS_REFUND_ENABLED', true),
            'pattern_code' => (string) env('SMS_REFUND_PATTERN', ''),
        ],
        'order_paid' => [
            'enabled'      => (bool) env('SMS_ORDER_PAID_ENABLED', false),
            'pattern_code' => (string) env('SMS_ORDER_PAID_PATTERN', ''),
        ],
        'enrollment_ready' => [
            'enabled'      => (bool) env('SMS_ENROLLMENT_READY_ENABLED', false),
            'pattern_code' => (string) env('SMS_ENROLLMENT_READY_PATTERN', ''),
        ],
        'wallet_campaign_credited' => [
            'enabled'      => (bool) env('SMS_WALLET_CREDITED_ENABLED', false),
            'pattern_code' => (string) env('SMS_WALLET_CREDITED_PATTERN', ''),
        ],
    ],
];
```

- Setting key: `SettingKeyEnum::SMS_NOTIFICATIONS = 'sms_notifications'`, group `sms`, `type` `json`, no secret fields.
- Read precedence **per option field**: DB value if present → `config('sms.notifications.<key>')` → hardcoded safe default (`enabled = false`, `pattern_code = ''`). The API returns only the resolved value; the winning layer is never exposed to the frontend.
- There is no `source`/`is_default` field in the response by design — do not add one.
- The `log_type` values match the `type` column already written by `SmsChannel`/`IpPanelSmsService` into `sms_logs`, so the section can later show delivery stats without a new taxonomy.

---

## 4. Enrollment providers

Purpose: configure the external providers that enrollments are provisioned into. Same interaction model as payment gateways, but the settings objects are the **flat arrays already stored** under `SettingKeyEnum::IMS`, `MOODLE`, `SPOT_PLAYER`, `SKYROOM`, `NILIROOM` and read by `AbstractIntegrationService::resolveConfig()`.

### 4.1 Endpoints

| Purpose | Method | Endpoint |
| --- | --- | --- |
| List providers with schema | `GET` | `/api/v1/admin/settings/enrollment-providers` |
| Read one provider | `GET` | `/api/v1/admin/settings/enrollment-providers/{provider}` |
| Save one provider | `PUT` | `/api/v1/admin/settings/enrollment-providers/{provider}` |

`{provider}` is the **provisioning provider key** (`ProvisioningProviderEnum`), not the raw setting key. Unknown key → `404`.

| `{provider}` (API) | Setting key | Label | Adapter |
| --- | --- | --- | --- |
| `ims` | `ims` | IMS | `ImsService` |
| `moodle` | `moodle` | مودل | `MoodleService` |
| `spotplayer` | `spot_player` | اسپات‌پلیر | `SpotPlayerService` |
| `skyroom` | `skyroom` | اسکای‌روم | `SkyroomService` |
| `niliroom` | `niliroom` | نیلی‌روم | `NiliroomService` |

`moodle_quiz` (present in `ProvisioningProviderEnum` and `ProvisioningProviderRegistry`) has **no credentials of its own** — it runs on the `moodle` configuration. It is intentionally absent from this list and must not appear as a form: `GET/PUT .../enrollment-providers/moodle_quiz` → `404`.

### 4.2 Fields per provider

All values are stored flat inside the provider's setting object (group `integrations`). The `Default` column is the value returned when the DB has no stored value; where a config path exists it is shown, otherwise the adapter's own hardcoded default applies.

#### `ims`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | yes | no | `config('services.ims.base_url')` |
| `credentials` | `api_key` | password | yes | **yes** | `config('services.ims.api_key')` |
| `advanced` | `timeout` | number | no | no | `15` |

Ready condition (`ImsService::validateConfig`): non-empty `base_url` **and** `api_key`.

#### `moodle`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | yes | no | `config('services.moodle.base_url')` |
| `credentials` | `token` | password | yes | **yes** | `config('services.moodle.token')` |
| `credentials` | `auth_userkey_token` | password | yes | **yes** | `config('services.moodle.auth_userkey_token')` |
| `advanced` | `default_role_id` | number | no | no | `5` |
| `advanced` | `default_login_redirect_script` | text | no | no | `/my/` |
| `advanced` | `timeout` | number | no | no | `15` |

`auth_userkey_token` is the web-service token used to call `auth_userkey_request_login_url`, i.e. the key that lets a customer log in to Moodle from the shop. It is therefore **required**, not optional: an enabled Moodle provider without it can provision courses but cannot produce a working SSO login URL.

Ready condition: non-empty `base_url` **and** `token` **and** `auth_userkey_token`. `MoodleService::validateConfig()` currently checks only `base_url` + `token` — implementation must extend it, otherwise the admin panel would report `ready: true` for a provider whose student login is broken.

#### `spotplayer`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `endpoint` | url | yes | no | `https://panel.spotplayer.ir/license/edit/` |
| `credentials` | `api_key` | password | yes | **yes** | `config('services.spotplayer.api_key')` |
| `testing` | `sandbox` | boolean | no | no | `false` |
| `advanced` | `timeout` | number | no | no | `15` |

Ready condition: non-empty `endpoint` **and** `api_key`. Note the key is `endpoint`, not `base_url`.

#### `skyroom`

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | no | no | `https://www.skyroom.online/skyroom/api` |
| `credentials` | `api_key` | password | yes | **yes** | `config('services.skyroom.api_key')` |

Ready condition: non-empty `api_key`.

#### `niliroom`

Niliroom is the panel behind BBB live sessions, and the only one: `live_session_bbb` names the delivery method, and the retired BigBlueButton integration has no fallback (ADR 0013). Teachers are sent to Niliroom through a provider-native login grant. Its credentials are a plain base URL + API key pair, shaped like `skyroom`.

| Group | `key` | Type | Required | Sensitive | Default |
| --- | --- | --- | --- | --- | --- |
| `general` | `enabled` | boolean | yes | no | `false` |
| `connection` | `base_url` | url | yes | no | `config('services.niliroom.base_url')` |
| `credentials` | `api_token` | password | yes | **yes** | `config('services.niliroom.api_token')` |

Ready condition: non-empty `base_url` **and** `api_token`. `NiliroomService` reads this configuration, so the state badges reflect live behaviour.

### 4.3 Response — `GET /enrollment-providers`

```json
{
  "message": "عملیات با موفقیت انجام شد.",
  "data": [
    {
      "key": "ims",
      "setting_key": "ims",
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

`state` is computed server-side and read-only (the response lists all six providers; only `ims` is shown above):

| Field | Meaning |
| --- | --- |
| `enabled` | `$service->isEnabled()` — admin intent. A disabled provider that a delivery option requires is still recorded in the enrollment's provisioning plan, with readiness `disabled`, and the aggregate `provisioning_status` becomes `MANUAL_ACTION_REQUIRED` (it is never silently skipped). No provisioning job runs against a non-`ready` provider until an admin fixes it. |
| `configured` | `$service->validateConfig()` — required fields are filled. |
| `ready` | `enabled && configured` — the provider can actually provision. Show this as the badge; a disabled-but-configured provider is normal, an enabled-but-unconfigured provider is an admin error (`readiness: invalid`). |

`GET /enrollment-providers/{provider}` returns **one item shaped exactly like an element of `data`** (`{ key, setting_key, label, state, schema, settings }`), not wrapped in an array.

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

- Required fields are validated **only when they are semantically needed**: `enabled` is always required; the provider's readiness fields are required when `enabled = true`. Saving a disabled provider with empty connection fields is allowed (admins stage configuration before switching it on). A failure is `422` with the field name(s) in `errors`.
- Sensitive fields follow the shared rule: omitted or `null` keeps the stored secret, `***REDACTED***` keeps it too, `""` clears it.
- **Full replace semantics** for non-sensitive fields: any documented non-sensitive key that is omitted is reset to its config/default value. (Send the whole object from the form, as with payment gateways.)
- Response: `200` with the saved provider object including the recomputed `state`.
- Returns `404` for `moodle_quiz` and any unknown key.
- Storage: written through `SettingsService::set($provider->settingKey(), ...)` with `$key->secretFields()` encryption and an `AdminActionLog` entry (existing behavior for these keys — no new mechanism).

> **Why flat and not nested under `config`?** Payment gateways nest provider fields under `config` because they also carry shared display fields (`label`, `description`, `icon`). Enrollment providers have no shared display fields and are already stored as flat arrays, so the payload matches storage 1:1. The frontend generic form renderer is unaffected: it renders groups from `schema` and submits the `settings` keys as-is.

---

## 5. Frontend rendering rules (all three areas)

1. `GET` the list (or the single resource), then build the form from `schema` group by group, in the order the groups and fields are returned. Do not hardcode field lists, labels, or gateway/provider lists.
2. Bind inputs to `settings` using the field `key` as the literal request path.
3. For `type: "password"` with `sensitive: true`, render the masked placeholder as a hint, not as a value. Submit `null` when the admin has not typed a new secret.
4. `PUT` the whole form; re-render from the response instead of assuming the save succeeded locally (the server is the source of truth for masked values and `state`).
5. Show validation errors from `errors` keyed by field path (`422`), and a permission error on `403`.
6. Never display decrypted secrets; the API never returns them.

## 6. Implementation checklist (backend)

Required for these endpoints to exist:

1. `config/sms.php` with `gateways` and `notifications` blocks (section 3.5), plus a `services.niliroom` entry (`base_url`, `api_key`).
2. `SettingKeyEnum`: `SMS_IPPANEL = 'sms.ippanel'` (group `sms`, secret field `api_key`), `SMS_NOTIFICATIONS = 'sms_notifications'` (group `sms`), and `NILIROOM = 'niliroom'` (group `integrations`, secret field `api_key`).
3. `SettingSecretRedactor::SECRET_FIELDS` + `SettingsService::SKIP_MEDIA`: add `sms.ippanel`, `niliroom`, and `skyroom` (see gaps below).
4. Data classes: `SmsGatewaySettingData` (+ per-gateway `schema()`), `SmsNotificationSettingsData`, `EnrollmentProviderSettingData` (+ per-provider `schema()`), following `MellatGatewaySettingData`.
5. Controllers under `app/Http/Controllers/Api/Admin/Settings/`: `SmsGatewaySettingsController`, `SmsNotificationSettingsController`, `EnrollmentProviderSettingsController` — thin, `Gate::authorize(...)`, all logic in `SettingsService`/actions. The enrollment controller accepts `niliroom` alongside the provisioning enum keys.
6. Routes in `routes/Api/V1/admin/setting.php`, next to `payment-gateways`.
7. Extend `MoodleService::validateConfig()` to require `auth_userkey_token` (section 4.2).
8. Optionally seed defaults in `SettingsSeeder` (group `sms` / `integrations`).
9. Scribe fixtures under `resources/responses/admin/settings/...` plus `@responseFile` docblocks, per `.ai/rules/api-docs.md`.
10. Tests: index/show/update per area (permission `403`, validation `422`, secret masking and "omit keeps secret" behavior, config fallback, `moodle_quiz`/unknown-key `404`).
11. Update `docs/Digestions/DIGEST_API_INTERFACES.md` and `DIGEST_DATA_MODELS.md` once implemented (AGENTS.md requirement).

## 7. Known gaps to resolve while implementing

These are existing inconsistencies this contract surfaces. Each is a decision or fix needed before/while building the endpoints:

| # | Gap | Impact |
| --- | --- | --- |
| 1 | `skyroom` is missing from `SettingSecretRedactor::SECRET_FIELDS` and `SettingsService::SKIP_MEDIA`, although `SettingKeyEnum::SKYROOM::secretFields()` lists `api_key`/`secret`. | Reading `skyroom` through the generic settings API returns decrypted secrets, and the `witImages` media lookup runs on a credentials array. |
| 2 | ~~`BbbService` reads `default_attendee_pw` / `default_moderator_pw`, but the seeder, config, and `SettingKeyEnum::BIG_BLUE_BUTTON::secretFields()` use `default_attendee_password` / `default_moderator_password`.~~ Resolved by #113: the BigBlueButton integration, its passwords and its setting key are gone (ADR 0013). | BBB meeting joins no longer exist to ignore the passwords. |
| 3 | `SettingsSeeder` stores no `enabled` for `spot_player` and reads `config('services.spotplayer.base_url')`, which does not exist (the config key is `endpoint`). | The seeded value has a null endpoint, and `enabled` currently falls back to the config file instead of the DB. |
| 4 | `create_studets` / `update_studets` / `create_enrollments` / `update_enrollments` flags are seeded for `ims`/`moodle` but read nowhere. | They are exposed as editable settings that do nothing. Exclude them from `schema` until they are wired, or remove them. |
| 5 | `payment-gateways` documents sensitive values as `••••••` and returns `settings: []` when unset, while the actual mask is `***REDACTED***`. | This contract standardizes on `***REDACTED***` + always-object `settings`; the payment endpoint should be aligned separately. |
| 6 | No global SMS master switch; each notification option has its own `enabled`. | Confirm whether a single "SMS notifications on/off" toggle is wanted in addition to per-option switches. |
| 7 | ~~Nothing reads a gateway-level `enabled` today: `IpPanelSmsService::validateConfig()` only checks `api_key`/`from`, and `SmsChannel` sends unconditionally.~~ Resolved by #106: `IpPanelSmsService` resolves the stored gateway setting and treats `enabled` as a kill switch, recording a skipped `sms_logs` row instead of throwing. | The gateway toggle and the per-option toggles are both guaranteed switches: #107 wires each notification option into the send path. |
| 8 | ~~`OtpSmsNotification` hardcodes pattern `mdoe1j1587`, and `RefundCompletedNotification` has no pattern at all.~~ Resolved by #107: `SmsMessage` carries only `content`, `parameters` and `type`, and `SmsChannel` reads the option's `pattern_code` from the settings via the message `log_type`. | Pattern codes now live only in the notification-option settings. |
| 9 | `MoodleService::validateConfig()` checks `base_url` + `token` only, while `auth_userkey_token` is what makes customer SSO login work. | Without extending it, the panel reports `ready: true` for a Moodle provider that cannot log students in. |
| 10 | ~~`niliroom` has no adapter, no `ProvisioningProviderEnum` case, and no config entry yet.~~ Resolved by #54/#113: `NiliroomService` is the adapter, and it stays out of `ProvisioningProviderEnum` deliberately — Niliroom has no resource to create, so `live_session_bbb` plans zero providers (ADR 0013). | It remains a live-session-only integration, configured here and read at join time. |
