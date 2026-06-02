# Enrollment Detail Refactor — Design Document

**Date:** 2026-05-30
**Status:** Design Approved

## Problem

The current `GetEnrollmentDetailAction` and integration services (`app/Services/Integrations/`) have several pain points:

1. **Per-type branching in API response** — `delivery_block` uses a PHP union type with per-provider if/else, making the response unpredictable for frontend consumers.
2. **Inconsistent provisioning data** — Each provider stores different shapes in `provisioning_data`; some fields are regenerated on each API request (BBB join_url), some stored at provision time (SpotPlayer license).
3. **Siloed features** — Digital assets (files) only appear for `DIRECT_DOWNLOAD` types; quizzes don't appear at all despite being needed for all types.
4. **Skyroom** — Completely unimplemented despite being a primary delivery method.
5. **Join URLs generated eagerly** — BBB join URL regenerated on every enrollment detail request, even if user never opens the classroom.

## Goals

1. **Uniform response shape** — All delivery types return the same top-level blocks. The frontend renders by reading a `type` discriminator field.
2. **Standardized provisioning data contract** — Every provider writes a predictable shape to `enrollment.provisioning_data`.
3. **Lazy join URLs** — Join URLs for BBB and Skyroom are served via a dedicated `GET /enrollment/{uuid}/join` endpoint, called only when the user clicks "Join Classroom".
4. **Files universal** — Digital assets appear as a top-level block for ALL delivery types.
5. **Quizzes universal** — Moodle handles quizzes for all types via `moodle_quiz_course_id` in `details_json`.
6. **Skyroom integration** — Skyroom gets a service class + provisioning job.

---

## Decisions Made (Brainstorm Results)

| Topic | Decision |
|---|---|
| Uniform response shape | All blocks at top-level for every delivery type |
| Join URLs | Separate endpoint — NOT in enrollment detail response |
| Quizzes (all types) | `moodle_quiz_course_id` in `details_json` for non-Moodle delivery types |
| Skyroom setup | `room_id` in `details_json` |
| `delivery_access` type | `type` field directly on the block |
| `provisioning_data` schema | Standardized contract across all providers |

---

## Target Response Structure

### Top-level Enrollment Detail

```json
{
  "uuid": "0195f7c0-...",
  "enrollment_status": { "value": "active", "label": "فعال" },
  "access_start_date": null,
  "access_end_date": null,
  "external_enrollment_id": null,
  "notes": null,

  "product": { ... },

  "teachers": [
    { "uuid": "...", "first_name": "...", "last_name": "...", "bio": "...",
      "avatar_url": "...", "rate": 0, "gender": { "value": "male" }, "social_links": [] }
  ],

  "files": [
    { "id": 1, "short_name": "جزوه کلاس", "full_name": "جزوه کلاس برنامه‌نویسی",
      "thumbnail_url": "...", "download_url": "..." }
  ],

  "quizzes": [
    { "cmid": 42, "name": "آزمون میان‌ترم", "type": "quiz",
      "url": "https://moodle.../mod/quiz/view.php?id=42",
      "state": 1, "score": "85.00", "timecompleted": null }
  ],

  "delivery_access": { /* type-specific, see below */ },

  "review_info": {
    "has_reviewed": false,
    "review": null
  },

  "certificate_info": {
    "is_available": false,
    "certificate_url": null
  },

  "survey_block": {
    "url": null,
    "message": null
  }
}
```

### Per-Delivery-Type `delivery_access` Shapes

**BBB / Skyroom:**
```json
{
  "type": "live_session_bbb",
  "session_label": "کلاس آنلاین",
  "join_url_path": "/api/v1/shop/my-courses/0195f7c0-.../join"
}
```

**Moodle:**
```json
{
  "type": "lms_moodle",
  "course_url": "https://moodle.example.com/course/view.php?id=5",
  "completed": false,
  "course_grade": null
}
```

**SpotPlayer:**
```json
{
  "type": "video_platform_spotplayer",
  "license_key": "ABC-123-DEF-456",
  "player_url": "https://spotplayer.example.com/play/..."
}
```

**In-Person:**
```json
{
  "type": "in_person",
  "address": "تهران، خیابان ولیعصر، پلاک ۱۲۳",
  "map_url": "https://neshan.org/..."
}
```

**Direct Download:**
```json
{
  "type": "direct_download"
}
```

---

## `details_json` Contract (per Delivery Type)

Format: `field_name* required, field_name? optional`

| Delivery Type | Fields |
|---|---|
| `LIVE_SESSION_BBB` | `meeting_id*`, `attendee_password?`, `moderator_password?`, `auto_create_meeting?`, `moodle_quiz_course_id?` |
| `LIVE_SESSION_SKYROOM` | `room_id*`, `moodle_quiz_course_id?` |
| `LMS_MOODLE` | `moodle_course_id*` |
| `VIDEO_PLATFORM_SPOTPLAYER` | `spot_id*` (rename from `course_id`), `moodle_quiz_course_id?` |
| `IN_PERSON` | `address?`, `map_url?`, `moodle_quiz_course_id?` |
| `DIRECT_DOWNLOAD` | `moodle_quiz_course_id?` |
| Any (cross-cutting) | `ims_course_code?` |

---

## `provisioning_data` Standard Contract

All providers follow this shape under `provisioning_data.providers.{name}`:

```json
{
  "providers": {
    "{name}": {
      "status": "success|failed|pending",
      "provisioned_at": "ISO 8601",
      "data": { /* provider-specific, see below */ },
      "sync": { /* optional: progress/sync data written by SyncMoodleProgressJob */ }
    }
  }
}
```

### data shapes per provider

**moodle** (primary delivery):
```json
{
  "moodle_user_id": 99,
  "moodle_username": "1234567890",
  "moodle_course_id": 5,
  "course_url": "https://moodle.../course/view.php?id=5",
  "login_path": "/my"
}
```
**moodle_quiz** (quiz-only for non-Moodle types):
```json
{
  "moodle_user_id": 99,
  "moodle_username": "1234567890",
  "moodle_course_id": 12
}
```
**both moodle & moodle_quiz have sync:**
```json
"sync": {
  "synced_at": "2025-01-01T10:00:00Z",
  "completed": false,
  "course_grade": null,
  "activities": [
    { "cmid": 42, "name": "آزمون میان‌ترم", "type": "quiz",
      "url": "https://...", "state": 1, "score": "85.00", "timecompleted": null },
    ...
  ]
}
```

**spotplayer:**
```json
{ "license_key": "ABC-123", "player_url": "https://..." }
```

**bbb:**
```json
{ "meeting_id": "meeting-xyz" }
```

**skyroom:**
```json
{ "room_id": 42, "skyroom_user_id": 99 }
```

**ims:**
```json
{ "course_code": "CS101", "enrollment_id": "42", "student_id": "7" }
```

---

## Data Flow

```
[Product Creation]
  Admin creates ProductDeliveryOption
    → details_json: provider config (meeting_id, room_id, moodle_course_id, spot_id, ...)
    → moodle_quiz_course_id if non-Moodle type has quizzes
    → ims_course_code if IMS reporting needed

[Payment → Provisioning]
  Payment completes
    → ProvisionPaidResourcesListener checks config(provisioning_trigger)
    → Dispatches provisioning jobs based on details_json fields:
      - ims_course_code?  → ProvisionImsEnrollmentJob
      - delivery_method=LMS_MOODLE  → ProvisionMoodleEnrollmentJob
      - moodle_quiz_course_id? AND delivery!=LMS_MOODLE → ProvisionMoodleQuizJob (NEW)
      - delivery_method=VIDEO_PLATFORM_SPOTPLAYER → ProvisionSpotPlayerEnrollmentJob
      - delivery_method=LIVE_SESSION_BBB → ProvisionBbbEnrollmentJob
      - delivery_method=LIVE_SESSION_SKYROOM → ProvisionSkyroomEnrollmentJob (NEW)

[Enrollment Detail API]
  GET /api/v1/shop/my-courses/{uuid}
    → GetEnrollmentDetailAction
    → Reads provisioning_data.providers for credentials (NOT calling external APIs)
    → Reads productable.digitalAssets (files) — universal for all types
    → Reads providers.moodle.sync.activities OR providers.moodle_quiz.sync.activities (quizzes)
    → Builds uniform response — delivery_access is the only type-specific block

[Join URL Endpoint]
  GET /api/v1/shop/my-courses/{uuid}/join  (NEW)
    → Resolves delivery method
    → BBB: calls BbbService::buildJoinUrl() live
    → Skyroom: calls SkyroomService::createLoginUrl() live
    → Returns { url: "https://...", type: "live_session_bbb", expires_at: "..." }
```

---

## What to Build / Modify

### New Files

| File | Purpose |
|---|---|
| `app/Data/Shop/MyCourses/DeliveryAccessData.php` | Single DTO with `type` discriminator + nullable provider-specific fields |
| `app/Data/Shop/MyCourses/EnrollmentQuizData.php` | Quiz item DTO (from Moodle activities) |
| `app/Data/Shop/MyCourses/EnrollmentQuizzesBlockData.php` | Quizzes block wrapper DTO |
| `app/Data/Shop/MyCourses/EnrollmentFilesBlockData.php` | Files block wrapper DTO |
| `app/Services/Integrations/SkyroomService.php` | Skyroom REST API client |
| `app/Jobs/Provisioning/ProvisionMoodleQuizJob.php` | Provisions Moodle user for quiz-only course |
| `app/Jobs/Provisioning/ProvisionSkyroomEnrollmentJob.php` | Creates/assigns Skyroom user, stores ID |
| `app/Actions/Shop/MyCourses/GetJoinUrlAction.php` | Generates BBB/Skyroom join URL on demand |
| `app/Data/Shop/MyCourses/JoinUrlData.php` | Response DTO for join URL endpoint |

### Modified Files

| File | Changes |
|---|---|
| `EnrollmentDetailData.php` | Replace union type with `DeliveryAccessData`, add `files`, `quizzes` |
| `GetEnrollmentDetailAction.php` | Rewrite: remove `match($deliveryMethod)`, read providers predictably, universal files/quizzes |
| `ProvisionBbbEnrollmentJob.php` | Strip join URL generation; store only `meeting_id` |
| `ProvisionMoodleEnrollmentJob.php` | Strip `course_info` blob — only store IDs, course_url, login_path |
| `ProvisionSpotPlayerEnrollmentJob.php` | Rename `course_id` → `spot_id` in data extraction |
| `ProvisionImsEnrollmentJob.php` | No change (already clean) |
| `SyncMoodleProgressJob.php` | Support `moodle_quiz` provider key (non-Moodle delivery types) |
| `ProvisionPaidResourcesListener.php` | Add dispatch for `ProvisionMoodleQuizJob` and `ProvisionSkyroomEnrollmentJob` |
| `GetDeliveryDetailsValidationRulesAction.php` | Add Skyroom rules, SpotPlayer `spot_id` rename |
| `LiveSessionBbbBlockData.php` | Remove union references (no longer needed as a unique block) |
| `LiveSessionSkyroomBlockData.php` | Remove union references |
| `LmsMoodleBlockData.php` | Keep for internal Moodle service use; remove from response union |
| `VideoPlatformSpotplayerBlockData.php` | Remove union references |
| `InPersonBlockData.php` | Remove union references |
| `DigitalAssetBlockData.php` | Remove union references |

### Removed (or made internal)

| File | Reason |
|---|---|
| `DigitalAssetBlockData`, `DigitalAssetFileData` | Files now top-level; these may be replaced by `EnrollmentFilesBlockData` |
| `MoodleActivityData` | May be replaced by `EnrollmentQuizData` |

---

## Route Changes

```php
// New endpoint for join URL generation
Route::get('/my-courses/{enrollment}/join', [MyCourseController::class, 'joinUrl'])
    ->name('shop.my-courses.join');
```

---

## Implementation Order

1. **Data layer first** — `DeliveryAccessData`, `EnrollmentQuizData`, `EnrollmentQuizzesBlockData`, `EnrollmentFilesBlockData`, `JoinUrlData`
2. **Skyroom Service** — `SkyroomService` with core methods (findOrCreateUser, assignUserToRoom, createLoginUrl)
3. **Provisioning jobs** — Rewrite `ProvisionBbbEnrollmentJob` (strip join URL), create `ProvisionMoodleQuizJob`, create `ProvisionSkyroomEnrollmentJob`
4. **SyncMoodleProgressJob** — Add `moodle_quiz` support
5. **ProvisionPaidResourcesListener** — Wire new jobs
6. **GetEnrollmentDetailAction** — Full rewrite with uniform response
7. **GetJoinUrlAction** — New endpoint
8. **Remove dead DTOs** — Cleanup old block data classes no longer needed
9. **Tests** — Update existing tests, new tests for Skyroom, quiz provisioning, join URL endpoint
