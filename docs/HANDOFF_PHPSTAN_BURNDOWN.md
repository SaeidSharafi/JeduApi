# Handoff: PHPStan Baseline Burn-Down (Session Continuation)

> Last updated: 2026-08-08. Commit `f2545651` — `chore(phpstan): continue baseline burn-down and fix static analysis errors`.

## Objective

Burn down the PHPStan level-6 baseline incrementally with idiomatic Laravel fixes. Acceptance gate: full run green (`vendor/bin/phpstan analyse app --no-progress`, exit 0) with matched baseline deletions.

**Current state: full run GREEN, baseline at 36 entries** (was 86 paths at start, then 65, now 36).

## Standing Constraints (user directives — do NOT violate)

1. "We should not fix the phpstan errors for the sake of fixing it — the goal is clean code." Research community FIRST. Vendor-package typing limitations are ignorable → baseline them.
2. Demo/fake/test classes (`app/Services/Fakes/*`) are IGNORED by design — they mirror the real services' constructor contract for seamless DI swap via `app/Providers/DemoServiceProvider.php`. Keep their `property.onlyWritten` errors baselined.
3. No `@phpstan-ignore` comments, no `assert()`/inline `@var` to override types, no type casts, no type widening. Fixes must be "the Laravel way."
4. Commits: Conventional Commits (`type(scope): imperative description`). Run `sail bin pint --dirty --format agent` before finalizing PHP changes. PEST only for tests.
5. Check `.ai/rules/index.md` before editing files. Trust `docs/Digestions/DIGEST_*.md` over inferred architecture.

## Commands

- Run: `vendor/bin/phpstan analyse app --no-progress`
- Regenerate baseline: `vendor/bin/phpstan analyse app --no-progress --generate-baseline` (takes 2–5 min)
- Count entries: `grep -c 'message:' phpstan-baseline.neon`
- Pint: `vendor/bin/sail bin pint --dirty --format agent`
- Tests: `vendor/bin/sail artisan test --compact --filter=...`

## Work Completed (this session, all in commit f2545651)

### PHPStan fixes (idiomatic, "Laravel way")

- `app/Traits/HasMedia.php` — removed dead `method_exists()` guard in `loadMediaWitVariant()` (all consumers use Mediable). Resolved `function.alreadyNarrowedType` x6.
- `app/Http/Controllers/Api/Shop/AvatarController.php` — removed `use (&$media)` in `destroy()` closure (undefined var). Resolved `closure.unusedUse` x1.
- `app/Notifications/Order/RefundCompletedNotification.php` — removed dead `$smsService` property + constructor + import. Resolved `property.onlyWritten` x1.
- `app/Rules/PublishedProductExistRule.php` — removed pointless `DataAwareRule` interface, `$data` property, `setData()`. Resolved `property.onlyWritten` x1.
- `app/Http/Controllers/Api/Shop/SuggestSearchController.php` — removed stale `@return JsonResponse` + unused import. Resolved `return.phpDocType` x1.
- `app/Notifications/Auth/OtpSmsNotification.php` — removed stale `@return array<string>` on `via()`. Resolved `return.phpDocType` x1.
- `app/Http/Controllers/Api/Shop/HomePage/PartnerController.php` + `StudentStoryController.php` — removed 9-type union closure return annotations on `rememberHomepageContent()` callbacks. Resolved `return.unusedType` x12.
- `app/Traits/IsProductable.php` — corrected `getProductableAttachment()` docblock to `@return array<string, array<int, PrivateFileData>>`. Resolved `return.type` x3.
- `app/Services/GlobalSearchService.php` — `searchWithDatabase()` docblock → `@return LengthAwarePaginator<int, BlogPost>|LengthAwarePaginator<int, Product>`. Resolved `return.type` x2.
- `app/Services/SWRCacheService.php` — propagated `@template T` + `Closure(): T` through `rememberHomepageContent()`, `rememberSearchSuggestions()`, `rememberTrendingContent()` wrappers. Resolved `argument.templateType` x1 (the inner `self::remember()` call).
- `app/Rules/CheckDiscountConfigurationRule.php` — `setData()` now returns `static` + `return $this` (DataAwareRule contract). Resolved `method.childReturnType` x1.
- `app/Models/Teacher.php` + `app/Models/Vendor.php` — added `@method Collection<string, Collection<int, Media>> getAllMediaByTag()` annotations (Mediable is untyped upstream). KEPT — proven working fix.
- `app/Http/Controllers/Api/Admin/User/TeacherController.php` — typed closure params (`Collection $item, string $tag`, `Media $mediaItem`).

### Related prior-session work included in the commit

- `app/Services/Integrations/MoodleService.php` — replaced dead properties with `getDefaultRoleId()` / `getLoginPath()` accessors reading config.
- `app/Observers/ProductableAvailabilityObserver.php` — narrowed `Model` to `Model&ProductableContract<Model>`.
- `app/Providers/FullTextSearchProvider.php` — `Builder::macro('withPgroonga')` closure typed `Builder`, connection instance-checked.
- `app/Providers/AuthServiceProvider.php` — removed unused `cached` auth provider registration + dead import.
- `app/Notifications/SmsChannel.php` — documented `OtpSmsNotification|RefundCompletedNotification` union.
- Test time-stability fixes in `AdminAuditMiddlewareTest` (travelTo) + `GenerateComplianceReportActionTest` (frozen baseTime) + `CourseAttendanceProxyControllerTest` (assertJsonPath).
- Minor: `declare(strict_types=1)`, `final` classes, spacing on discount condition classes.

## Current Baseline Content (36 entries) — Next Targets

Full list is in `phpstan-baseline.neon`. Clusters remaining, ordered by fixability:

1. **`collect()` template resolution (4)** — `app/Http/Controllers/Api/Shop/Teacher/CourseController.php` (TKey/TValue/keyBy) + `app/Actions/Shop/Student/GetEnrollmentDetailAction.php` (TKey/TValue). Root cause: `$courses = data_get($response, 'data', [])` is `mixed`, and `?? collect()` bare calls. Look at `ImsService::getTeacherCourses()` return — typed `array<string, mixed>`, but `data` key shape unknown. Community-first: consider typing the service response or extracting with a typed local.
2. **`method.childReturnType` morphMany generics (6)** — `Course`/`Seminar`/`DigitalAsset::products()` return `MorphMany<Product, $this>` from trait `IsProductable`, contracts declare `MorphMany<Product, TModel>`. **Both `static` (in contract) and `static` (in trait) backfired** — `TDeclaringModel` is invariant, `morphMany()` inherently returns `$this`. This is a known larastan limitation (same family as method.notFound). Recommend BASELINE unless community found a pattern.
3. **`generics.notSubtype` ProductCardData (3)** — `app/Query/CategoryQueryService.php` `@return` declares `LengthAwarePaginator<ProductCardData>` (missing TKey) + `Collection<int, ProductCardData>` not subtype of `TModel`. Check how the collection is actually built — likely `map()` result typed wrong.
4. **`trait.unused` (2)** — `app/Traits/HasMetaTagsMigration.php`, `app/Traits/ValidatesMetaTags.php` used zero times. Verify with `grep -rn` then DELETE if truly dead.
5. **`method.unused` (1)** — `app/Services/Integrations/AbstractIntegrationService::formatValidationErrors()` unused. Verify callers, delete if dead.
6. **Builder scope resolution (2)** — `CategoryController` `publishedAndVisible()`, `ProductSearch` `forListing()` undefined on `Builder`. Likely scopes declared in traits/models not visible to PHPStan — check if scope methods exist and add proper `@mixin` or model-level declaration.
7. **Misc singles (6)** — verta datetime x2 (vendor limitation → baseline), `Route::signatureParameters` expects array (check call), `Enrollment::$external_enrollment_id` non-empty-string (cast/property type), `MoodleActivityData` state int→enum at call site, `DigipayClient::deliver()` array shape (check caller), `GlobalSearchService::search()` return union vs declared `LengthAwarePaginator<int, Model>` (fix docblock to union or abstract).
8. **Small fixables (4)** — `UniqueCivilIdRule` `$id on object|string`, `ProductCategoryCondition` match arm ALL vs ALL always true (remove dead arm), `EventServiceProvider` `observe() on int|string` (model resolution), `EnsureAdminNumericIdsMiddleware` `signatureParameters`.
9. **Baseline-by-design (4)** — `Fake*` properties x4 (demo doubles), `ImageManipulator::defineVariant()` static-call-on-instance (vendor), `PersonalAccessToken::tokenable()` Attribute-vs-MorphTo (Sanctum override).

## Session Memory / Agent State

- No background agents currently running. Earlier librarian research concluded: larastan `#[Scope]` closure limitation is known (PR #2048 closed unmerged, PHPStan #3770 open) → baselined. Plank Mediable `getAllMediaByTag()` untyped even on master → `@method` annotations or baseline.
- graphify hook auto-rebuilds on commit (background). Ignore unless asked.
- Previous scratch files (`stan3.txt` etc.) were cleaned up earlier — do not recreate.

## Next Steps (recommended order)

1. Fix `collect()` cluster (#1) — biggest clean-code win. Start with `GetEnrollmentDetailAction` line 74 (`?? collect()`) and CourseController `$courses` typing.
2. Delete dead traits (#4) + unused method (#5) after grep verification.
3. Fix `GlobalSearchService::search()` return docblock (#7) + dead match arm (#8).
4. Investigate Builder scopes (#6) — check `app/Models` for scope definitions and `@mixin`/`Builder` generics.
5. Re-run full phpstan → regenerate baseline (drops fixed entries) → pint → run affected tests → commit with Conventional Commit message.
6. Final state check: baseline should shrink further toward ~15 entries (mostly vendor limitations + Fake* by-design).

## Verification Evidence (before commit f2545651)

- Full phpstan run: `[OK] No errors` (exit 0) at 36-entry baseline.
- Tests: CourseAttendanceProxyControllerTest + AdminAuditMiddlewareTest → 84 passed (147 assertions).
- Earlier this session: TeacherControllerTest + CategoryApiTest 17 passed; ProductTypesenseIndexTest + ProductQueryServiceFeatureTest 44 passed.
- Pint applied to all dirty files.
