# Price bounds are per-scope catalogue constants

A shopper narrowing a shop listing had no API-stated price range: the frontend derived the slider's domain from the cards it happened to have loaded plus the bounds already in the URL, so the domain shifted as the shopper paged, collapsed to a point when one product matched, disappeared when nothing matched, and needed a padding hack to stay movable. Every filterable listing and global search response now carries its scope's **Price Bound** — the lowest and highest **Purchase Price** in that scope — as `metadata.filters.price`.

We decided a Price Bound is a **constant of its scope**, not a function of the request:

1. **Per scope, never per request.** The bound is computed over the endpoint's default scope with every request filter ignored, including the request's own `min_price`/`max_price`. Each listing bounds its own productable type; a category endpoint additionally includes that category; `/search` bounds the requested `productable_type`, or every shopped type (courses, seminars, digital assets — never bundles) when none is requested, and omits the bound entirely when the request cannot return products.
2. **One basis for the bound, the price filter and the card.** A Purchase Price is the cheapest currently-buyable price across a product's published Product Delivery Options, after any featured or promotion discount — the materialized `product_prices.min_price`, the number a product card shows and the number `sortBy=price` orders by.
3. **`null` members mean an empty scope.** The bound is `{min: int|null, max: int|null}`; both members null means the scope has no priced product. It is deliberately not `0..0`, which a slider renders as a legitimate free-to-zero range.
4. **One cached aggregate per scope.** The bound is read through `CacheStore::flexible()` under one key per scope, in the existing `CacheTag::Catalog` group, so a stale bound never costs a request its latency. Invalidation comes free from the writers that already bump `Catalog` — product and delivery-option writes, the twice-daily full price sweep and the 15-minute featured-price check, availability recomputation, category writes, and discount reindexing — so there is no new scheduled job and no new tag. An empty scope is cached as a bound whose *members* are null, never as a null value, because the gateway cannot distinguish a stored null from a miss.
5. **Bundle listings are out of scope.** `/bundles` carries no bound: a bundle is not part of the filterable shop catalogue, is never indexed for search, and has no price range to describe.

## Considered Options

**Scope-derived bounds.** Deriving the bound from the filters the shopper has applied was rejected. It is what the frontend did: the domain moves as filters change, so the handles jump under the shopper's finger, a filter that matches one product collapses the slider into an unusable point, and a filter that matches nothing removes it precisely when the shopper needs it to relax the filters. It would also turn every request into an aggregate over its own result set instead of a cache read.

**Per-filter-option result counts, and Typesense facet plumbing.** Returning a count per filter option (including per price bucket) was rejected. A sidebar shows labels only; counts would need Typesense facet plumbing plus a second aggregation per request, and a price slider needs a domain, not a histogram. Adding counts later is a separate decision, not an omission here.

## Consequences

- Because the bound is filter-independent, it is computed once per scope and cached; requests that differ only in filters reuse the same entry.
- The existing paginated payload is untouched: the bound lives in the envelope's opaque `metadata` slot, which stays an empty array where there is nothing to report.
- A per-result-set price domain is no longer a supported contract; clients render the catalogue constant and stop deriving a domain from the page of cards they received.

**References:** `docs/Digestions/CODEBASE_DIGEST.md` (navigation), `docs/Digestions/DIGEST_API_INTERFACES.md` (envelope contract), `docs/Digestions/DIGEST_CORE_LOGIC.md` (bound service and cache behaviour), `docs/adr/0014-single-cache-gateway-explicit-invalidation.md` (the cache gateway this relies on).
