---
paths:
  - 'app/Actions/Admin/**'
  - 'app/Jobs/**'
  - 'app/Listeners/**'
  - 'app/Observers/**'
  - 'app/Services/**'
  - 'app/Enums/System/**'
  - 'config/**'
  - 'tests/Architecture/**'
---

# Storefront cache revalidation

The storefront caches every public shop response in Next's Data Cache under tags it defines, and clears
them through `POST {storefront}/api/revalidate`. Its accepted tags are mirrored in
`App\Enums\System\StorefrontTag`, and a write that changes public content must announce the tags it
disturbed:

```php
StorefrontCacheInvalidated::dispatch(...StorefrontTag::catalogWith(StorefrontTag::HomeBlocks));
```

`StorefrontCacheInvalidated` → `QueueStorefrontCacheRevalidation` → `RevalidateStorefrontCacheJob` →
`StorefrontRevalidationService` (queued, retried only for transport/5xx failures). The storefront answers
`400` for a tag outside its allowlist **and clears nothing**, so only `StorefrontTag` cases may be sent.
See ADR 0019 for why the dispatch sits where it does.

## A new feature needs a decision, not a default

Before wiring a new write path, answer both of these in the task; do not leave them implicit:

1. **Does this change anything the storefront renders?** If yes, dispatch. If the resource has no tag in
   `StorefrontTag` / the storefront's allowlist, **stop and ask the developer** whether the storefront
   should add one — never invent a tag, and never skip the dispatch silently.
2. **What else does it affect?** Derive the *related* clears yourself; the obvious tag is rarely the whole
   answer:
   - a teacher rename or photo lives inside product cards → `catalog()` + `home:blocks`
   - a category change also renders in the header menu and the footer → `catalog:categories` +
     `settings:categories` + `settings:footer`
   - anything that can appear in a home block (products, categories, blog posts, sliders) → `home:blocks`
   - site title or favicon (page metadata: tab title, `og:site_name`, icon) → `settings:site`
   - ratings, teachers, terms → `catalog()`; blog ratings also `blog:posts`
   - price and availability → dispatch from inside `UpdateProductPricingJob` /
     `UpdateProductAvailabilityJob`, after their own `invalidate()`. They are the last writers of the price
     projection and the availability snapshot, and notifying earlier races them, which lets the storefront
     cache a stale response under a fresh TTL.

Coarse tags are deliberate: clearing a collection that did not change costs one re-fetch, missing one
serves stale content until its TTL.

## Required upkeep

- Add the write path and its tags to `storefrontDispatchInventory()` in
  `tests/Architecture/StorefrontRevalidationTest.php`. That test is the mapping's only real guard — it
  also checks the ordering rule inside the two jobs and a negative list that must stay silent.
- Never add a `StorefrontTag` case without checking the storefront's `REVALIDATE_TAGS`
  (`src/lib/revalidateRequest.js`): the architecture test pins a frozen copy, so drift fails the suite
  instead of failing in production.
- Deliberately silent, because nothing the storefront caches comes from them: `cache:invalidate`, the
  organization page, vendor, related products (the storefront calls no `/related` endpoint), and the
  spreadsheet import (users only).

## Already checked — do not re-derive

- A seminar or productable edit **does** refresh search results: `ProductableAvailabilityObserver` plus
  `SynchronizeProductSearchIndexJob` bump `CacheTag::Search`. Scout's `toSearchableArray()` is a document
  definition, not an invalidation hook.
- The spreadsheet engine (`app/Services/ImportExport/**`) imports **users only**
  (`SpreadsheetResourceEnum` has one case), and no storefront tag covers users.

## Environments

`STOREFRONT_URL` has **no fallback**: it must be an address the API *container* can reach.
`FRONTEND_SHOP_DOMAIN` is the browser-facing payment-redirect host and resolves to the container itself,
so reusing it produced `cURL error 7` on every write. `STOREFRONT_REVALIDATE_SECRET` must equal the
storefront's `REVALIDATE_SECRET`. Missing either value is a logged no-op. Nothing is sent without a
running queue worker, `queue:work` workers keep the config they booted with (`queue:restart` after
changing a secret; `queue:listen` reloads per job), and revalidation is per storefront instance.
