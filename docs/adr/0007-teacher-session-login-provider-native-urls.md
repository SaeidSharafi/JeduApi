# Teacher session login uses provider-native URLs, never API-created rooms

Teachers need a way to reach their live sessions (BBB/Niliroom and Skyroom seminars) without credentials: open the session panel/room as a presenter. The student flow already exists (`GetJoinUrlAction`), but it is keyed to enrollments and provisioning data, uses attendee access for Skyroom (`access: 1`), and points BBB at the legacy self-hosted `BbbService` — none of which fit a teacher.

We decided: a new teacher-only endpoint `GET /api/v1/shop/teacher/courses/{deliveryOption:uuid}/join` returns a single-use, short-lived provider-native URL, resolved by `delivery_method`:

- **Skyroom** → `SkyroomService::createLoginUrl(roomId, 'user-'.$userId, nickname, access: 2, ttl: 3600)` — presenter access, no user creation, no SMS. Teacher's room `id` comes from the delivery option's `details_json` (`room_id`).
- **BBB** → Niliroom login grant when the Niliroom service is configured: sync user (`PUT /users/eshop/{subject}`), upsert enrollment as `teacher` (`PUT /rooms/{room}/enrollments/{user}`), then issue `POST /login-grants` with the stored `nili_room_id` (room public ID in `details_json`). The grant URL logs the teacher into the panel and redirects to the room page; expires in 5 minutes, single-use.
- **BBB fallback** → when Niliroom is disabled/unconfigured, `BbbService::buildJoinUrl(meetingId, fullName, role: MODERATOR)` using the BBB 3.x `role` parameter (password param deprecated in 3.x, unchanged checksum).

Rooms are always created manually by staff in the provider panels and referenced by ID stored in the delivery option's `details_json` (`room_id` for Skyroom, `nili_room_id` for Niliroom). We never create rooms via provider APIs — the integration is login/join/info only. This avoids provider rate limits (Skyroom docs warn against per-class room churn), keeps the room lifecycle with the staff who configure seminars, and means the teacher flow needs no provisioning-state dependency: it reads `details_json` directly, unlike the student flow which reads enrollment `provisioning_data`.

Provider-native URLs mean the provider owns expiry, single-use, and signature. We considered creating Skyroom users programmatically and sending credentials by SMS (gives teachers dashboard-level control), but that would store or regenerate passwords, risk duplicate accounts per teacher, and contradict the "teacher access, not editing access" constraint — deferred until a manual-control need is proven. We considered resolving Niliroom rooms by `external_reference` via `GET /rooms`, but Niliroom has no find-by-external-reference endpoint; storing the room public ID is cheaper and matches every other `{room}` path param.

Consequences: `LiveSessionBbbDetailsData` gains `nili_room_id`; `BbbService` gains a role-based join (student path keeps password); teacher URL generation lives in a new `GetTeacherJoinUrlAction` + controller mirroring `TeacherMoodleSsoController` ownership checks (teacher pivot on the delivery option, 403 otherwise). SMS-based credential delivery remains a possible future path and is intentionally out of scope.

**Related:** ADR 0012 drops BBB from the admin settings surface, so this fallback is configured by deployment environment rather than by an admin form. The Niliroom-primary decision above is unchanged.

> Superseded in part by ADR 0013: the BBB fallback bullet above no longer stands, and neither does the Consequences clause that gives `BbbService` a role-based join. Niliroom replaces BBB entirely, `live_session_bbb` is served by Niliroom alone, and the BBB delivery path is retired. The rest of this ADR — provider-native URLs, never API-created rooms, room IDs in the delivery option's details, and the `GetTeacherJoinUrlAction` + controller shape — still holds.

