# Cache Usage & Invalidation Audit — JeduShop

**Type:** Bug hunt / read-only audit. No code changed.
**Date:** audit of current working tree.
**Method:** exhaustive grep sweep for every cache API (`Cache::`, `cache()`, `SmartCache::`, `SWRCacheService::`, `CacheKeysEnum`, `cache_invalidation`), then traced each cached value back to the code that writes the underlying data (models, actions, jobs, commands, mass updates, relation syncs, raw SQL), and checked whether an invalidation exists on every write path.

## 0. Cache infrastructure in use

| Mechanism | Where configured | Notes |
|---|---|---|
| Laravel Cache facade | `config/cache.php` | default store = `redis`, `CACHE_STORE=redis` (`.env`); `e2e` store in `config/cache.php:94-98` |
| `iazaran/smart-cache` | `composer.json`; no published config (package defaults) | `SmartCache` facade, `swr()`, `flushPatterns()`, `lock()` |
| SWR wrapper | `app/Services/SWRCacheService.php` | `swr()` == SmartCache `flexible()`; **synchronous** refresh (see §4.2) |
| Model-event invalidation | `app/Observers/InvalidationObserver.php` + `config/cache_invalidation.php` | `saved`/`deleted` only, registered in `app/Providers/EventServiceProvider.php:33-38` |
| Key registry | `CacheKeysEnum` (`app/Enums/System/CacheKeysEnum.php`) | key + TTL per logical cache |
| Request-scoped memo | `app/Services/RequestDataCacheService.php` | not a persistent cache |
| DB column used as cache | `products.price_data_cache` (`app/Services/ProductPriceService.php`) | denormalized JSON cache |

### 0.1 How pattern invalidation actually works (important)

`app/Services/CacheInvalidationService.php:81` calls `SmartCache::flushPatterns($patterns)`.
The package implements this by iterating a global key index (`_sc_managed_keys`), not by scanning the store
(`vendor/iazaran/smart-cache/src/Services/CacheInvalidationService.php:23-40`).
Consequences that affect several findings below:

* Only keys written **through SmartCache** are tracked and therefore invalidatable by pattern.
* The index is persisted lazily: every 10 changes (`vendor/iazaran/smart-cache/src/SmartCache.php:163,1180`) and in `__destruct()` (`:1226-1229`).
* If `_sc_managed_keys` is lost/evicted, untracked keys can no longer be pattern-invalidated (they still expire by TTL).
* `max_tracked` defaults to `null` = unlimited (`vendor/iazaran/smart-cache/config/smart-cache.php:213`), so the index itself grows unbounded in Redis.

---

## 1. Complete cache inventory (read/write site → data writer → invalidation)

| # | Cache (key) | Read site | Data writers | Invalidated? |
|---|---|---|---|---|
| 1 | `settings.all` (forever) | `app/Services/SettingsService.php:165` | `Setting::setValue()` via `SettingsService::set()` `:104-105`; `SettingObserver` `:14,19`; `EncryptSettingSecretsCommand.php:85-86`; `UpdateHeaderSettingAction.php:31`; `UpdateFooterSettingAction.php:32` | ✅ yes |
| 2 | `shop.homepage.sliders` (SWR 300/900) | `app/Http/Controllers/Api/Shop/HomePage/SliderController.php:31` | `Slider` model saves | ✅ `config/cache_invalidation.php:95-97` |
| 3 | `shop.homepage.partners` / `shop.course.partners` / `shop.partners` (SWR 300/900) | `PartnerController.php:37-48` (`PartnerShowInEnum.php:14-21`) | `Partner` model saves | ✅ `config/cache_invalidation.php:98-102` (all 3 keys) |
| 4 | `shop.homepage.student-stories:<md5>` (SWR 300/900) | `StudentStoryController.php:31-60` | `StudentStory` saves (create/update/delete) | ⚠️ partial — see **F5** |
| 5 | `shop.category.<slug>.good-for-start.courses-<limit>` (1800 s) | `GoodForStartCoursesController.php:36-52` | Product / PDO / Course / Category / Categorizable / promotion changes | ⚠️ mostly — see **F8** |
| 6 | `search:<md5>` (SWR 300/900) | `GlobalSearchService.php:118-120` | Product, PDO, PDO-discount-price, Course, Seminar, DiscountPromotion, Review, BlogPost saves | ⚠️ partial — see **F6** |
| 7 | `search:suggest:<md5>` (SWR 3600/14400) | `GlobalSearchService.php:78-80` | same as #6 + Category | ⚠️ partial — see **F6** |
| 8 | `student_quizzes:<userId>` (SWR 300/900) | `app/Http/Controllers/Api/Shop/Student/QuizController.php:26` | Moodle (external) | ❌ no invalidation — **F13** |
| 9 | `teacher_quizzes:<userId>` (SWR 300/900) | `app/Http/Controllers/Api/Shop/Teacher/QuizController.php:31` | Moodle (external) | ❌ no invalidation — **F13** |
| 10 | `all_permissions` (forever) | `StaffPasswordLoginController.php:79`; `StaffOtpAuthenticationController.php:73` | `permissions:sync` (deploy `deploy.php:44-47`), spatie role/permission CRUD | ❌ **never invalidated — F1** |
| 11 | `all_roles` (declared) | — | — | ❌ unused config — **F16** |
| 12 | `AccessToken::<sha256>` (360 s) | `PersonalAccessToken.php:24` | token delete/logout/ban | ⚠️ only single-model delete — **F2** |
| 13 | `token_<id>::id_<env>` (360 s) | `PersonalAccessToken.php:37` | tokenable (User/Staff) changes | ❌ **F2/F3** |
| 14 | `digipay_access_token` | `DigipayAuthenticator.php:21,55` | `clearToken()` `:28-31` | ❌ `clearToken()` has **zero callers** — **F4** |
| 15 | `discounts.handler_registry.cache` (forever) | `DiscountHandlerRegistry.php:61,190` | `discounts:clear-cache` command | ⚠️ command not scheduled — **F12** |
| 16 | `database.pgroonga_enabled` (forever) | `PgroongaService.php:21` | DB extension install/removal | ❌ never invalidated — **F14** |
| 17 | OTP value/marker/attempts (`otp_*`) | `OtpManagerService.php:163-289` | OTP generate/verify (same service) | ✅ self-managed |
| 18 | Locks (`price-indexing`, `product-availability-indexing`, `otp_lock_*`, order/product/refund locks) | `IndexAllProductPricesCommand.php:25`, `CheckExpiredFeaturedPricesCommand.php:28`, `IndexAllProductAvailabilityCommand.php:23`, `OtpManagerService.php:267`, `ApproveOrderAction.php:43`, `CreateProductAction.php:27`, `UpdateProductAction.php:32`, `CreateRefundAction.php:40`, `DigipayRefundProcessor.php:37` | n/a | ✅ all released in `finally`/`block()` |
| 19 | E2E full flush | `ResetE2eEnvironmentAction.php:37,69` | — | ✅ flushes `cache` DB (same DB as app default store, `config/database.php:175-182`) |
| 20 | `products.price_data_cache` (DB column) | `ProductPriceService.php:37,266,328` | `updatePriceIndexForProducts()` via pricing job/action | ✅ invalidated by `UpdateProductPricingJob.php:67-77` |

---

## 2. Findings — HIGH

### F1 — `all_permissions` cache is never invalidated (forever-stale staff permission list)
**Severity: High (correctness / security-adjacent)**

* Read/written: `Cache::rememberForever(config('cache.keys.all_permissions'), …)` in
  `app/Http/Controllers/Api/Admin/Auth/StaffPasswordLoginController.php:79`
  and `app/Http/Controllers/Api/Admin/Auth/StaffOtpAuthenticationController.php:73`.
* Key declared: `config/cache.php:122`.
* **No `forget`/`flush` for this key exists anywhere in `app/`, `config/`, or the commands** (verified by full-repo grep).
* The only thing that ever clears it is a full `cache:clear`/`optimize:clear`, which happens on deploy
  (`deploy.php:65-66`) before `permissions:sync` runs (`deploy.php:44-47`).

**Impact:** runtime permission/role changes (admin creating/renaming permissions, `permissions:sync` run outside a deploy) never appear in the `permissions` array returned by staff login. Cached forever, so it stays wrong until the next deploy or a manual cache flush. Spatie's own `spatie.permission.cache` is unrelated and does not cover this key.

**Related place that updates the data but does not clear the cache:** every spatie permission/role write, and `deploy.php`'s `permission:update` task.

---

### F2 — Access-token cache survives bulk token revocation (banned/deleted users stay authenticated ≤6 min)
**Severity: High (security)**

* Cache: `cache()->remember("AccessToken::{$hashedToken}", 360, …)` — `app/Models/PersonalAccessToken.php:24`.
* Cleanup hook only fires on the Eloquent `deleted` **model event** — `app/Models/PersonalAccessToken.php:64-71`.
* Bulk deletes bypass model events. Confirmed call sites:
  * `app/Actions/Admin/User/BanUserAction.php:23` — `$user->tokens()->delete();`
  * `app/Actions/Admin/Staff/BanStaffAction.php:23` — `$staff->tokens()->delete();`
  * `app/Actions/Admin/User/DeleteUserAction.php:50` — `$user->tokens()->delete();`
* There is **no per-request `is_banned` check** anywhere: grep for `is_banned` finds only model casts/fillable
  (`app/Models/User.php:46,164`, `app/Models/Staff.php:33,57`) and the login-time `UserBannedException`.
  The `deleted` event is therefore the *only* enforcement mechanism, and the cache defeats it.
* Logout is fine (`currentAccessToken()->delete()` is a model delete → event fires): `app/Http/Controllers/Api/Shop/Auth/LogoutController.php:28`, `app/Http/Controllers/Api/Admin/Auth/StaffLogoutController.php:34`.

**Impact:** a banned/deleted user's bearer token keeps resolving through the cached token for up to 360 s.

---

## 3. Findings — MEDIUM

### F3 — Cached `tokenable` (User/Staff) model is not invalidated on user changes
**Severity: Medium**

* `app/Models/PersonalAccessToken.php:35-42` caches the tokenable model under `token_<id>::id_<env>` for 360 s.
* The only invalidation is in `deleted()` (`:69`) — i.e. **only when a token row is deleted**, never when the *user* changes.
* `User`/`Staff` profile updates, ban/unban (`BanUserAction.php:18-21`), or role changes do not clear it.

**Impact:** for up to 6 minutes after a profile/ban change, authenticated requests can observe the old user snapshot (including `is_banned=false`, avatar/name, etc.). Compounds F2.

---

### F4 — Cached Digipay access token is never invalidated when credentials change
**Severity: Medium**

* `app/Services/Payment/Digipay/DigipayAuthenticator.php:19-26` returns the cached token; `:55` writes it with TTL ≈ `expires_in - 300` (≈55 min by default).
* `clearToken()` exists (`:28-31`) but has **zero call sites** (verified by full-repo grep; only the class itself references it).
* `DigipayConfigRepository` reads client-id/secret/username/password from settings (which *are* invalidated, see #1), but the already-minted token is not.

**Impact:** after an admin rotates Digipay credentials, the gateway keeps using the old token until it naturally expires (up to ~1 hour), causing auth failures against the new credentials.

---

### F5 — Student-story homepage cache is not invalidated by Course/Category changes
**Severity: Medium**

* Cached query depends on relations: `whereHas('categories', …)` and `whereHas('courses', …)`
  — `app/Http/Controllers/Api/Shop/HomePage/StudentStoryController.php:36-47`.
* Invalidation for `StudentStory` exists: `config/cache_invalidation.php:92-94` (`shop.homepage.student-stories*`).
* Invalidation for `Course` (`:60-65`) and `Category` (`:71-76`) contains **no** student-story pattern.
* `$story->courses()->sync()` / `categories()->sync()` in `UpdateStudentStoryAction.php:27-28` and `CreateStudentStoryAction.php:25-26` do **not** fire the parent model's `saved` event either (harmless here because `$story->update()` runs first, but it means pivot-only writes elsewhere would not invalidate).

**Impact:** renaming/re-slugging a course or category leaves the story lists keyed by the old value stale for the SWR fresh window (300 s, absolute 900 s).

---

### F6 — `search:*` / `search:suggest:*` invalidation has gaps and a race
**Severity: Medium**

Gaps (writers that change searchable data but do not clear the search cache):
* **Category slug change** → only the index sync is dispatched (`app/Observers/CategorySearchIndexObserver.php:15-23`); the Category map clears only `search:suggest:*`, not `search:*` (`config/cache_invalidation.php:71-76`). Cached `search:*` payloads carry `category_slugs` facets/filters (`GlobalSearchService.php:143`), so they go stale.
* **DigitalAsset** searchable-field changes (`full_name`, `slug`, `description`, …) → `ProductableAvailabilityObserver.php:54-69` dispatches only `ProductSearchIndexInvalidated`. `DigitalAsset` is **not** in the invalidation map (`App\Models\DigitalAsset` absent from `config/cache_invalidation.php`), so neither `search:*` nor `search:suggest:*` is cleared. Suggestions stay stale up to the 3600 s fresh TTL.
* **Bundle** is a productable (`app/Models/Bundle.php:17`) but is **not** registered with `ProductableAvailabilityObserver` (`app/Providers/EventServiceProvider.php:40-46`) and is not in the map. `UpdateBundleAction.php:22` changes `status`/`slug`/`full_name` with no event dispatched at all. `DeleteBundleAction.php:15` archives a bundle with no availability/search invalidation.
* **Teacher / Vendor** are not observed and not in the map, while product cards/search index are derived from them.

Race:
* Cache invalidation happens synchronously on model save, but the Typesense index update is **async**
  (`ProductSearchIndexInvalidated` → `QueueProductSearchIndexSynchronization` → `SynchronizeProductSearchIndexJob`).
* `SynchronizeProductSearchIndexJob.php` **does not clear any cache** after updating the index. A search request arriving between invalidation and index sync re-populates `search:*` from the not-yet-updated index and holds it for 300 s.

---

### F7 — Discount-price index can be stale while caches are cleared (rebuilds stale data)
**Severity: Medium**

* `ProductDiscountIndexer::reIndexComplete()` (`app/Services/Discounts/ProductDiscountIndexer.php:33-62`) truncates all discount prices (`:38`), then **returns early when there are no active promotions without dispatching any `ProductCacheInvalidated` event** (`:41-45`). The hourly `discounts:reindex-all` (`bootstrap/app.php` schedule) therefore removes all discounts from the DB but leaves previously cached discounted prices in `good-for-start` (TTL 1800 s), `search:*`, and homepage caches until they expire.
* `DiscountPromotionStatusUpdateController.php:32-34` toggles `is_active` and does **not** dispatch `RegeneratePromotionDiscountPricesJob`. The `DiscountPromotion` save does clear caches (`config/cache_invalidation.php:77-81`), but the `product_delivery_option_discount_prices` rows are still present, so the immediately-rebuilt cache recomputes the *same discounted price*. The change only takes effect after the next `discounts:reindex-all`.
* Async re-index makes this a general race: `UpdateDiscountPromotionAction.php:60` / `CreateDiscountPromotionAction.php:60` dispatch the regen job after commit, while the observer clears caches at commit — a request in between rebuilds from the old index.

---

### F8 — `ProductDeliveryOptionDiscountPrice` invalidation misses the good-for-start pattern
**Severity: Medium-Low**

* `config/cache_invalidation.php:55-59` clears `shop.homepage.content`, `search:*`, `search:suggest:*` — but **not** `shop.category.*.good-for-start.courses*`, even though this model changes the price rendered in those cards (`GoodForStartCoursesController.php:47-51` → `ProductPriceService`).
* The `Product` entry (`:41-49`) does include the good-for-start pattern, so paths that route through `ProductCacheInvalidated`/`UpdateProductPricingJob` are covered. Direct saves of the discount-price model (seeders, tests, ad-hoc writes) leave good-for-start stale up to 1800 s.
* Similarly missing on `ProductDeliveryOptionDiscountPrice`: nothing triggers `Product` invalidation directly.

---

## 4. Findings — LOW / LATENT

### F9 — `RegenerateAllDiscountPricesJob` crashes on its own config shape (currently dead code)
`app/Jobs/Discounts/RegenerateAllDiscountPricesJob.php:27-30`:

```php
$keysToClear = config('cache_invalidation.map.'.Product::class, []);
foreach ($keysToClear as $key) {
    SmartCache::forget($key->key());
}
```

The `Product` map mixes a `CacheKeysEnum` with `['type' => 'pattern', …]` arrays (`config/cache_invalidation.php:41-49`). Calling `->key()` on the array element is a fatal `Error: Call to a member function key() on array`. The job is **never dispatched anywhere** (only the class definition exists), so this is latent. It also would not handle pattern entries the way `CacheInvalidationService` does.

### F10 — `shop.homepage.content` is invalidated but never populated
`CacheKeysEnum::HomePageContent` (`app/Enums/System/CacheKeysEnum.php:9`) appears in the invalidation map 11 times (`config/cache_invalidation.php:39,42,51,56,61,67,72,78,83,88,107`) and in `CacheInvalidationService` docblocks, but **no code ever reads or writes that key**. The home-page block endpoint (`HomePageContentController.php`) is uncached (`app/Actions/Shop/GetHomePageBlockAction.php` has no cache call). Either the cache is missing or the invalidation config is dead — currently the map entries are no-ops.

### F11 — `CacheInvalidationService` silently ignores documented `tag` entries
`config/cache_invalidation.php:30` documents `['type' => 'tag', 'value' => …]`, but `app/Services/CacheInvalidationService.php:49-62` only handles `CacheKeysEnum`, `string`, and `['type' => 'pattern']`. A tag entry would be silently dropped (no key cleared, no error). No tag entries exist today — latent trap.

### F12 — Discount handler registry is cached forever and its clear command is not scheduled
* `app/Services/Discounts/DiscountHandlerRegistry.php:190` writes `Cache::forever`; `:61-67` only re-discovers when the cache is empty **or** `app.debug` is true (`:55-59`).
* `app/Console/Commands/Discounts/ClearHandlerCache.php:34` is the only invalidator; `discounts:clear-cache` is **not** in the schedule (`bootstrap/app.php`) nor in `deploy.php` tasks — it is only cleared indirectly by `artisan:cache:clear` on full deploy (`deploy.php:65`).
* In production (`APP_DEBUG=false`) handler discovery is frozen between deploys; adding/renaming a handler without a deploy-wide cache clear leaves it undiscovered.

### F13 — Quiz caches have no invalidation
`student_quizzes:<id>` (`QuizController.php:26`) and `teacher_quizzes:<id>` (`Teacher/QuizController.php:31`) are SWR 300/900 with no `forget`/pattern anywhere. Any Moodle-side change (new quiz, submission, enrollment) is invisible for at least the fresh window (300 s) and the value is cached absolute 900 s; there is no event hook on quiz submission. Bounded, but there is no path to force-refresh.

### F14 — `database.pgroonga_enabled` cached forever, never invalidated
`app/Services/PgroongaService.php:21`. If the extension is installed/removed after first read, the boolean is wrong until the next full cache flush. Low impact, but a `rememberForever` with no invalidator.

### F15 — Bundle productable is not observed
See F6. `app/Models/Bundle.php:17` implements `ProductableContract` and has a cast `status` (`PublicationStatusEnum`), but `EventServiceProvider.php:40-46` registers only Course/Seminar/DigitalAsset. Bundle status/searchable-field changes never dispatch `ProductAvailabilityCacheInvalidated`/`ProductSearchIndexInvalidated`; they only self-heal when the hourly `products:index-availability` job happens to change a snapshot and dispatch search invalidation (`UpdateProductAvailabilityJob.php:94-101`).

### F16 — Dead / unbounded cache metadata
* `config/cache.php:123` declares `all_roles`; nothing reads it (only `all_permissions` is used).
* SmartCache's `_sc_managed_keys` index is unbounded (`vendor/iazaran/smart-cache/config/smart-cache.php:213`, `SmartCache.php:1174`), i.e. the "no tags (memory leak risk)" rationale in `app/Services/CacheInvalidationService.php:20-21` is replaced by a different unbounded Redis index.
* Pattern invalidation depends on that index; if it is evicted/lost, tracked keys become permanently pattern-unreachable until re-written (they still expire by TTL).
* `SynchronizeProductSearchIndexJob` performs the Typesense sync but never clears the search caches it logically invalidates (see F6).

---

## 5. Non-issues verified (checked, not bugs)

* `Cache::lock(...)` usages all release in `finally` (`IndexAllProductPricesCommand.php:69-71`, `CheckExpiredFeaturedPricesCommand.php:80-82`, `IndexAllProductAvailabilityCommand.php:59-61`) or via `->block()` (`OtpManagerService.php:267`).
* `SettingsService::set()` clears the settings cache (`:105`) and `SettingObserver` also does (`:14,19`) — redundant but correct.
* E2E reset flushes the same Redis DB the app cache uses (`config/database.php:175-182` + `docker-compose.e2e.yml:29-30`), so app cache is cleared between e2e runs.
* `$this->app->singleton(function ($app): RequestDataCacheService {...})` (`AppServiceProvider.php:65-67`) is valid: Laravel's `Container::bind()` handles closure abstracts via `bindBasedOnClosureReturnTypes()` (`vendor/laravel/framework/src/Illuminate/Container/Container.php:358-364`).
* Product mass-updates (`Course::products()->update(['slug' => …])` in `UpdateCourseAction.php:28`, and the Seminar/DigitalAsset equivalents) and `ProductDiscountIndexer`'s `upsert`/`truncate` bypass model events, but the parent model save or the `ProductCacheInvalidated` dispatch compensates in the checked paths — except where noted in F7/F8.
* `deleteDiscountPromotion` cascade: FK `onDelete('cascade')` (`database/migrations/2025_08_11_120749_create_product_delivery_option_discount_prices.php:22-26`) removes price rows, and the `DiscountPromotion` `deleted` observer clears the caches.

---

## 6. Suggested priority

1. **F1, F2** — security/correctness, unbounded lifetime or 6-min auth bypass.
2. **F4, F5, F6, F7, F8** — user-visible stale data with clear missing invalidations.
3. **F3, F13** — stale per-user state windows.
4. **F9–F16** — latent traps, dead config, and metadata growth.
