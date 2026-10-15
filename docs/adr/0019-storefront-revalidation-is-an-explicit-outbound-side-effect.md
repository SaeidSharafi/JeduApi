# Storefront revalidation is an explicit outbound side effect of the write path

The storefront (`shop.jedu.ir`) caches public shop responses in Next's Data Cache, tagged with a
vocabulary of its own, and exposes `POST {storefront}/api/revalidate` to clear them. Until now a
publish could take up to the tag's TTL to appear. This API now clears the affected tags as part of the
write that caused the staleness.

Three choices had to be made: **where** the dispatch is written, **what** it carries, and **when** it
is sent. They are recorded together because the third one is the non-obvious part.

1. **Explicit dispatch at each write site.** A mutating action or job that clears this application's
   cache now also dispatches `App\Events\StorefrontCacheInvalidated` with the storefront tags it
   disturbed. A listener queues `RevalidateStorefrontCacheJob`, which the
   `StorefrontRevalidationService` sends with the shared secret. This is the same shape the product
   cache events already use (`ProductCacheInvalidated` → listener → job), so there is one pattern, not
   two.

2. **The payload is the oldest thing that can be wrong, not the newest.** `StorefrontTag` is an enum
   of the 18 strings the storefront accepts, and `catalog()` returns the four catalogue collection
   tags so a catalogue write clears all of them. The storefront's tags are collection level already,
   and the TTLs there are minutes at most, so precision buys a re-fetch of collections that did not
   change in exchange for a class of bugs where one was forgotten. The enum also makes a tag the
   storefront would answer `400` to (and, per its `parseRevalidatePayload`, clear *nothing* for)
   unrepresentable from this side.

3. **Dispatch after the last backend invalidation for that change.** For a content edit that is the
   action itself. For price and availability it is *not*: `UpdateProductPricingJob` is the only writer
   of `products.price_data_cache` and the only caller of
   `ProductPriceService::updatePriceIndexForProducts()`, and it is queued, so the dispatching therefore
   lives inside that job (and inside `UpdateProductAvailabilityJob`), not in the actions that dispatch
   `ProductCacheInvalidated`.

**Why not hook the cache gateway.** Dispatching from `LaravelCacheStore::invalidate()` is a one-line
change that covers every writer at once, and it was rejected on evidence:

1. **It misses half the CMS.** Header, footer, about-us, contact and collaboration are written through
   `SettingsService::set()`, which calls `forget()` on `CacheKey::Settings` and never bumps a
   `CacheTag`. Sliders, partners and the organization page forget individual keys the same way. A
   gateway hook would never see those writes — including the handoff's own acceptance case, a
   header/footer edit.
2. **It cannot be specific enough to be useful.** Every `CacheTag::Catalog` bump would have to clear
   `catalog:courses`, `catalog:books`, `catalog:seminars`, `catalog:bundles` *and*
   `catalog:categories`, because the gateway knows the group and not the entity. `CacheTag::HomePage`
   has the same problem across sliders, stories, partners and blocks. That is precisely the
   over-clearing the storefront's TTL plan is trying to earn the right to stop doing.
3. **The cross-cutting rule is per write, not per tag.** `UpdateCourseAction` syncs the course's
   categories, so its payload must also carry `catalog:categories` and `settings:categories`; a
   category write must also carry `settings:footer`, because the footer embeds category names. No
   tag-level mapping can know that.
4. **It contradicts [ADR 0014](0014-single-cache-gateway-explicit-invalidation.md).** That decision
   says a missed invalidation must be visible in a diff rather than hidden in an event dispatch. The
   gateway is the wrong place to add a second, outbound side effect to `invalidate()`.

**Why not listen to the product events that already exist.** `ProductCacheInvalidated` and
`ProductAvailabilityCacheInvalidated` are dispatched from roughly fifteen commerce sites, so a
listener on them would need no new dispatch calls. It loses on ordering: the storefront clears its tag
and immediately re-fetches from this API, and a listener would race the queued recalculation job that
actually writes the new price or availability. Winning that race caches the *stale* response on the
storefront under a fresh TTL, after which the backend's own invalidation cannot reach it — the visible
symptom being "the webhook returned 200 and the price is still wrong", which is the bug this work
exists to remove.

**Why not de-duplicate or debounce the dispatch.** A bundle with many components, or a bulk import,
can produce many dispatches in a burst. De-duplication by tag-set (`ShouldBeUnique`) drops a superset
payload that arrives while a subset is still queued, which silently loses a tag; a union accumulator
needs a new `CacheKey`/`CacheTag` pair plus a `Cache::lock()` to be correct. Neither is needed at
current volumes: revalidating twice is idempotent, and the burst sites are handled by dispatching once
per batch instead (the spreadsheet import). This is revisited if the logs show it matters.

## Considered Options

- **Dispatch from `LaravelCacheStore::invalidate()`.** Rejected: misses key-based invalidation entirely,
  cannot express the entity, and hides an outbound side effect inside the cache module (see above).
- **A listener on the existing product cache events.** Rejected: it races the queued recalculation
  jobs and can cache stale responses on the storefront (see above).
- **One listener per storefront tag on `CacheTag` bumps.** Rejected for the same reasons as the
  gateway hook, plus it would make the two vocabularies look isomorphic when they are not: `Auth` and
  `Discounts` have no storefront meaning at all, and `Search` (Typesense) is not cached on the
  storefront.
- **Reusing the storefront's tag strings as `CacheTag` cases.** Rejected: they describe different
  systems with different lifetimes and different owners. `CacheTag`/`CacheKey` stay untouched by the
  storefront's vocabulary, and `StorefrontTag` stays untouched by this application's.
- **`dispatchAfterResponse` instead of a queued job.** Rejected: it runs in the same PHP process after
  the response, so a slow or unreachable storefront still occupies a worker and a PHP-FPM restart
  loses the work; the queue already exists and owns retries here.
- **Sending `paths` alongside the tags.** Deferred, not rejected. Every storefront page is
  `force-dynamic` today, so `revalidatePath` has no route cache to purge and the Data Cache tags are
  the whole mechanism. Paths become relevant once the storefront ships its CDN phase, and the payload
  builder is a single place to extend then.

## Consequences

- A forgotten dispatch is a real bug in a write path, exactly as a forgotten `invalidate()` is under
  ADR 0014. It is mitigated by enforcement rather than magic: `tests/Architecture/StorefrontRevalidationTest.php`
  pins the enum against a frozen copy of the storefront's `REVALIDATE_TAGS`, asserts every tag is
  dispatched from somewhere, and walks the inventory of write paths in the plan.
- The storefront answers `400` for an unknown tag and clears nothing, so drift between the two repos
  shows up as a failing architecture test here and a logged `rejected` body there. Both sides fail
  loudly rather than serving stale content quietly.
- An environment without `STOREFRONT_URL`/`STOREFRONT_REVALIDATE_SECRET` sends nothing and logs at
  debug: local development and the test suite are unaffected, and a half-deployed environment degrades
  to TTL-based freshness rather than erroring. `401` (secrets differ), `503` (the storefront is missing
  its own secret), `404` (its build predates the endpoint) and `405` are answered calls and are not
  retried; transport failures and other 5xx are.
- Revalidation is per storefront instance. The current deployment runs one container with no replicas,
  so one call clears everything; scaling the storefront requires fanning the call out or accepting that
  the other replicas serve until their TTL.
- Raising the storefront's TTLs is a separate, gated change in that repository. This decision is what
  makes it possible, not what performs it.

## Testing approach

The transport is tested against Laravel's HTTP fake: the request shape (URL, method, secret header,
`{"tags": ...}` body), every status class (permanent rejections do not throw, retryable failures do,
transport failures surface), and both no-ops (unconfigured environment, empty tag list). The dispatch
sites are tested at the event seam with `Event::fake([StorefrontCacheInvalidated::class])` and the real
action or job, asserting the exact tag list, because that is where the decision lives — which tags a
given write disturbs. The end-to-end claim (a publish is visible on the storefront within about a
second) needs a running storefront and is recorded as a manual verification step in the plan, not
implied by the unit tests.

**References:** `docs/Digestions/DIGEST_CORE_LOGIC.md`, `docs/plans/storefront-cache-revalidation-webhook.md`,
the storefront's `docs/caching-plan.md` §5 (contract and event → tag table) and
`src/lib/revalidateRequest.js` (allowlist), [ADR 0014](0014-single-cache-gateway-explicit-invalidation.md).
