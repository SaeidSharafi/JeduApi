# Fix plan — discount audit specs #148–#157

Source: the two-axis review of `refactor/discount-system` against `main` (`7918e2b7`).
Inputs: the discount limit & eligibility scenario audit, the Forgejo specs #148/#149/#150 (+ children
#152–#157) and tracker #151, and the repo's own rules (`AGENTS.md`, `.ai/rules/**`).

State of the branch when this plan was written: `fix/148-reward-granting` has been **merged in**
(merge commit `d9e20cb9`); the full suite is green and Pint is clean on that tip.

Legend — **Type**: `code` (behaviour change) · `doc` (contract/documentation drift) · `test` (suite hygiene) ·
`refactor` (design smell) · `spec` (the spec itself is wrong or silent).
**Severity**: high = money or lost sales; medium = wrong reporting / confusing behaviour; low = hygiene.

| # | Item | Axis | Type | Severity |
| --- | --- | --- | --- | --- |
| 0 | #148 reward granting — merged, needs verification on the merged tip | Spec | code | — |
| 1 | `product_specific` promotions still accept usage limits that can never work | Spec | code | medium |
| 2 | Tier ladder and value conditions measure two different amounts | Spec | spec | medium |
| 3 | A gift line can change a threshold depending on evaluation order | Spec | code | medium |
| 4 | A coupon deactivated after being applied escapes its own cap | Spec | code | low |
| 5 | "Admin sees reserved capacity" is only half true (orders, not promotions) | Spec | spec | low |
| 6 | `promotion.status_update.json` documents a handler shape that does not exist | Standards | doc | medium |
| 7 | Digest says the tier condition publishes an array; it publishes `TierData` | Standards | doc | low |
| 8 | The audit document itself is untracked and stale | Standards | doc | low |
| 9 | Registry-wide guard test lives in `Feature` instead of `Architecture` | Standards | test | low |
| 10 | Pure-logic tests live under `tests/Feature` | Standards | test | low |
| 11 | A controller test pins translated copy | Standards | test | low |
| 12 | Two copies of "count the reservations, compare to the cap" | Standards | refactor | low |
| 13 | Four test files rebuild the same fixtures | Standards | refactor | low |
| 14 | Unprefixed helpers leak into the whole Pest suite | Standards | test | low |
| 15 | `reserved_usage_count` loading copy-pasted at five call sites | Standards | refactor | low |
| 16 | A default argument that exists only for tests | Standards | refactor | low |
| 17 | #151 leftovers: wallet-credit label, deactivation UX | Spec | doc/spec | low |

---

## Part 0 — Already done

### 0. #148 reward granting is now on the branch

**In one sentence.** The reward work was finished on its own branch but never merged, so the branch could
not deliver the spec it was reviewed against.

**What was wrong.** `fix/148-reward-granting` (three commits) was not an ancestor of `refactor/discount-system`
and was not on `main` either, so pricing a cart still wrote wallet ledger rows.

**What was done.** Merged into `refactor/discount-system` as `d9e20cb9`. Two conflicts, both mechanical:
`tests/Pest.php` (both sides added a `require_once`, kept both) and
`docs/Digestions/DIGEST_CORE_LOGIC.md` (both sides edited the cart-action list and the evaluation section;
kept both, and stopped claiming that the wallet/gift credit actions mutate totals).

**Done when.** `vendor/bin/sail artisan test --compact --parallel` is green, and
`tests/Feature/Api/V1/Shop/Sale/RewardGrantingTest.php` covers: a cart read writes no wallet row; a completed
checkout writes exactly one; a repeated completion does not pay twice; a missing wallet does not fail completion.

---

## Part 1 — Behaviour worth fixing in code

### 1. `product_specific` promotions still accept usage limits that can never work

**In one sentence.** The admin can still type "max 100 uses" on a promotion type whose engine never reads,
counts, or enforces that number.

**What's wrong.** `usage_limit_total` and `usage_limit_per_customer` are accepted for
`type = product_specific` at [DiscountPromotionCreateData.php:61-62](../../app/Data/Admin/Discounts/DiscountPromotionCreateData.php#L61-L62),
even though product prices are materialised into `product_delivery_option_discount_prices` by the projection
engine (`ProductDiscountIndexer`) and never produce a cart-discount entry — so no usage row is written and the
counter never moves. Two lines below, `coupons` is correctly refused for the same type at
[:67](../../app/Data/Admin/Discounts/DiscountPromotionCreateData.php#L67) with
`prohibited_if:type,PRODUCT_SPECIFIC`.

**Why it matters.** A setting that silently does nothing is worse than an absent one: the admin believes a
budget is enforced, and finance believes a cap exists. #151 recorded this decision ("rejected … mirroring the
existing prohibition on coupons") but no code was written.

**How it should be.** Add the same conditional prohibition to both fields, exactly as `coupons` is done:

```php
'usage_limit_total'        => ['nullable', 'integer', 'min:1', 'prohibited_if:type,'.DiscountTypeEnum::PRODUCT_SPECIFIC->value],
'usage_limit_per_customer' => ['nullable', 'integer', 'min:1', 'prohibited_if:type,'.DiscountTypeEnum::PRODUCT_SPECIFIC->value],
```

Keep the `bodyParameters()` prose for those fields describing them as cart-checkout-only. Add a validation test
for both create and update (they share the DTO) and one test that a `cart_checkout` promotion still accepts them.
Note for existing local data: demo promotions of type `product_specific` must not carry these fields — check
`database/demo/discount_promotions.json`.

**Done when.** Saving a `product_specific` promotion with either limit returns a 422 naming the field, and a
`cart_checkout` promotion is unaffected.

### 3. A gift line can change a threshold depending on evaluation order

**In one sentence.** A cart threshold can be measured against a number that a gift action inflated earlier in
the same calculation.

**What's wrong.** `OrderContextData::thresholdBasis(false)` returns `subtotal_full_payment_items`
([OrderContextData.php:113-118](../../app/Data/Admin/Discounts/OrderContextData.php#L113-L118)), and
`GiftProductAction` adds the gift's list price to that field mid-calculation
([GiftProductAction.php:58](../../app/Services/Discounts/Cart/Actions/GiftProductAction.php#L58)). The digest
documents the nuance, but the consequence is a live ordering dependency: a `cart_value_over` condition
evaluated *after* the gift action measures a different cart than the same condition evaluated *before* it. The
gift costs the customer 0, so this is invented value.

**Why it matters.** The whole point of #153 was "one basis, one number, so preview and checkout cannot
disagree". A field that another action mutates reintroduces exactly that class of disagreement — and it is
invisible in tests that happen to order the rules the other way.

**How it should be.** Snapshot the full-payment-only basis at context-build time, next to
`effective_subtotal_all_items`, and have `thresholdBasis()` read the snapshot:

- add `int $effective_subtotal_full_payment_items` to `OrderContextData` (build it in
  `PromotionService::buildOrderContext()` as the sum of `total` over non-bundle, non-prepayment lines — the
  product-effective worth, same as `discountableTotal()`);
- keep the legacy `subtotal_full_payment_items` for the full-value audit trail (`full_value_grand_total`,
  admin preview) and stop using it as a measurement;
- leave the docblock nuance in place, corrected: the gift bump affects the *audit* total only.

Add a test that asserts the same threshold outcome for two rule orders (gift action before / after the value
condition) on one cart.

**Done when.** `thresholdBasis(false)` is a context-build snapshot that no cart action mutates, and the
ordering test passes both ways.

### 4. A coupon deactivated after being applied escapes its own cap

**In one sentence.** If a code is switched off after a customer applied it, the coupon's usage cap stops being
checked for that cart.

**What's wrong.** [PromotionService.php:266-274](../../app/Services/Discounts/PromotionService.php#L266-L274)
resolves the applied code with `->where('is_active', true)`. When the row is inactive, `$coupon` is `null`, so
`isCouponCapFullyReserved()` is skipped — the promotion is judged only by its own total cap. Reserving capacity
is still real (usage rows exist for that code), so the accounting and the check disagree.

**Why it matters.** It is a narrow hole, but it is the same class as #151's open "deactivation between apply
and checkout" item, and it means a cap can be blown by exactly the carts that were mid-flight. It is also the
kind of gap that later reads as "the cap occasionally doesn't work".

**How it should be.** Split the two questions that are currently conflated in one query:

- *cap accounting*: resolve the applied code **without** the `is_active` filter, so its reservation count is
  always consulted;
- *eligibility/UX*: keep the active check where it decides whether the code may be used, and give it the
  explicit message the deactivation-UX item (#151) asks for — the customer should be told the code is no longer
  available rather than silently seeing a zero discount.

Add a test: apply a code, deactivate the coupon row, then price the cart — the coupon cap must still be counted,
and the customer must get a clear message rather than a silent drop.

**Done when.** An inactive-but-applied code still counts against its cap, and the cart says why it is no longer
applied.

---

## Part 2 — Contract and documentation drift

### 6. `promotion.status_update.json` documents a handler shape that does not exist

**In one sentence.** The admin API example for "change a promotion's status" shows a rule payload that the API
never returns.

**What's wrong.** The example at
[promotion.status_update.json:26-28](../../resources/responses/admin/discount/promotion.status_update.json#L26-L28)
says `"handler": "App\\Services\\Discounts\\Cart\\Conditions\\CartValueCondition"` with
`"configuration": {"min": 50000}`. The real contract is the handler key `cart_value_over`
(`DiscountHandlerKey`, stored in the `rules.handler` column, written by
`DiscountPromotionRuleFactory::asCartValueCondition()`) with `operator` / `value` / `measure_whole_commitment`
— exactly as the sibling [promotion.show.json:26-30](../../resources/responses/admin/discount/promotion.show.json#L26-L30)
shows it, and as `CartValueConditionConfigData` validates it.

**Why it matters.** These fixtures *are* the published API documentation (`.ai/rules/api-docs.md`: response
examples are hand-written because they are the contract). An integrator copying this example sends a handler
class name and a `min` key that validation rejects. The commit that wrote it (`383775e2`) mirrored
`promotion.update.json`, which carries the same legacy shape — so the drift is inherited, not invented, but it
was re-published here.

**How it should be.** Regenerate the body from a real response (or copy `promotion.show.json`'s rule objects,
which match the code), keeping the fields the status-update endpoint actually returns. While there, align
`promotion.index.json`, `promotion.store.json` and `promotion.update.json` — they carry the same stale handler
shape today, so fixing only one file leaves three examples contradicting the endpoint they document. Add the
`cart_value_tier` and configuration-free `apply_tiered_percentage_off` shapes if the examples are meant to cover
the new handlers.

**Done when.** Every rule object in every `resources/responses/admin/discount/promotion.*.json` uses
`DiscountHandlerKey` values and the config keys their `ConfigData` classes accept.

### 7. Digest says the tier condition publishes an array; it publishes `TierData`

**In one sentence.** One line of `DIGEST_CORE_LOGIC.md` describes a payload the code no longer produces.

**What's wrong.** [DIGEST_CORE_LOGIC.md:698](../../docs/Digestions/DIGEST_CORE_LOGIC.md#L698) says
`CartValueTierCondition` "publishes `['min_amount' => int, 'percentage' => float]`", while the code publishes a
`TierData` object (and line 930 of the same file says so, and
`ApplyTieredPercentageOffAction` requires `instanceof TierData`). A reader (or a chatbot working from digests
only) would write the wrong consumer.

**Why it matters.** Digests are the reference for anyone without repo access — the rules require them to stand
alone. A self-contradicting digest is how a wrong contract gets re-implemented.

**How it should be.** Change the sentence to name `TierData` (`App\Services\Discounts\Configs\TierData`), which
is also what `CartValueTierConditionConfigData` carries in its `tiers` list. Grep the four digests for other
survivors of the pre-#155 shape (`min_amount` as a published selection, `include_prepayments`,
`ApplyTieredPercentageOffData` as config-bearing) and fix them in the same pass.

**Done when.** No digest describes the pre-#155 tier contract.

### 8. The audit document itself is untracked and stale

**In one sentence.** The document every one of these specs cites is not in git, and its status section still
lists findings this branch has fixed.

**What's wrong.** `docs/Architecture/Discount-Limit-Scenario-Audit.md` shows as untracked, so it is absent from
every diff and every review of this work. Its header says only I1–I4 + I15/I16 are implemented, and §9 says
"Not addressed: I5–I14 and I13, plus … the new I17" — but #149 fixed I13 and I17, #150 fixed I6 (partly) and
I8, and the merge just delivered I18/#148. Section 8's coverage-gap list is also partly obsolete (the
`CancelAbandonedOrdersCommand` release assertion, the `partially_refunded` usage-row assertion).

**Why it matters.** It is cited as the evidence base by all four specs. A reviewer following it will re-report
fixed findings and miss the real ones — which is exactly what a stale audit costs.

**How it should be.** Commit the document, and update the status header plus §9 to record what landed, with the
issues still genuinely open (I5, I7, I9–I14 minus what #149/#150 closed, #151's leftovers). Add a line at the
top saying which branch/commit delivered each item so the next reader can verify instead of trusting prose.

**Done when.** The file is tracked and its "implemented / not addressed" lists match `main` plus this branch.

---

## Part 3 — Test-suite hygiene

### 9. Registry-wide guard test lives in `Feature` instead of `Architecture`

**What's wrong.** `PrepaymentDiscountGuardTest` iterates every registered cart action
([line 92](../../tests/Feature/Services/Discounts/PrepaymentDiscountGuardTest.php#L92)). `.ai/rules/tests.md`
says directory-wide conventions belong in `tests/Architecture` as `arch()` rules (:43), and scope test files to
one target (:22).

**Why it matters / how it should be.** The test is *right* — the invariant it protects is the spec's core
("a newly added action cannot silently reintroduce the violation"). Only the address is wrong: it is a
suite-wide contract, not the behaviour of one class. Move the enumerating assertion to
`tests/Architecture` (or keep a thin per-action case in `Feature` and move the registry sweep), so the two kinds
of test stay distinguishable when someone runs a focused scope. Low risk, mechanical.

**Done when.** The registry sweep is an architecture test and still fails if a new markdown action marks down a
deposit.

### 10. Pure-logic tests live under `tests/Feature`

**What's wrong.** `DiscountableLinesTest` and `Conditions/CartValueTierConditionTest` assert DTO/handler logic
under `tests/Feature`, while tests.md asks for "pure logic in unit/integration tests; a feature test proves the
HTTP seam" (:38).

**Why it matters / how it should be.** Nothing is broken; the suite is just slower and the layer signal is
muddied — a reader cannot tell a seam test from a logic test. Move both to `tests/Unit` (or
`tests/Integration`, matching the sibling `tests/Integration/Services/Discounts`), keeping the shared
`Tests\TestCase` reset untouched.

**Done when.** Those files live in the layer their assertions belong to and still pass.

### 11. A controller test pins translated copy

**What's wrong.** [DiscountInfoControllerTest.php:144](../../tests/Feature/Api/V1/Admin/Promotion/DiscountInfoControllerTest.php#L144)
asserts the literal label `'Measure the Whole Commitment'`. tests.md says not to test plain DTOs with no logic,
and "if a test would still pass with the implementation deleted, delete it" (:32-35).

**Why it matters.** A copy edit in `lang/en/discount.php` — the normal way to fix a label — breaks a test that
is not about the label at all. The test then gets "fixed" by editing the assertion, which teaches nothing.

**How it should be.** Assert the stable contract instead: the key/handler identifier and the structure of the
payload (which fields exist, which enum values are offered), not the human text. If the copy itself is worth
pinning, pin it in a small lang test where a translator would look.

**Done when.** Changing the label wording does not fail the API test.

---

## Part 4 — Design smells (do last, one at a time)

### 12. Two copies of "count the reservations, compare to the cap"

**What's wrong.** `PromotionService::isTotalCapFullyReserved()` / `isCouponCapFullyReserved()`
([:291](../../app/Services/Discounts/PromotionService.php#L291), [:307](../../app/Services/Discounts/PromotionService.php#L307))
repeat the shape owned by `ResolvePerCustomerPromotionEligibilityAction::filterExhausted()`: count `active()`
rows, compare to a limit, decide "exhausted".

**Why it matters.** #150's whole safety argument is "one definition of active reservation used by preview,
checkout and reporting". Three near-copies of the counting rule is how those three drift apart again — the
expensive part is not the duplication, it is that only one of them gets fixed next time.

**How it should be.** Give the reservation predicate one owner (an action or a model scope) that answers
`exhausted(promotion|coupon, limit): bool`, and have the finder, the per-customer filter and the blocking
message ask it. Keep the grouped query (no N+1) — pass the pre-loaded counts into the predicate.

**Done when.** Exactly one place compares a reservation count to a cap number.

### 13. Four test files rebuild the same fixtures

**What's wrong.** `PromotionServiceTest::reservePromotionUsageSlot()` and
`DiscountPromotionControllerTest::reservePromotionCapacity()` create the same order + usage row; three more
files rebuild an `OrderContextData` (`DiscountableLinesTest::contextOf`,
`CartValueTierConditionTest::tierConditionContext`, `PrepaymentDiscountGuardTest::depositOnlyContext`).

**Why it matters / how it should be.** This diff already established the pattern for shared test support
(`tests/Support/Helpers/` + `tests/Pest.php`). Move the reservation-slot builder and the `OrderContextData`
builder there (as factories or helper functions) so the next change to the context constructor touches one
file, not five.

**Done when.** One helper builds a usage row and one builds a context; the old private builders are gone.

### 14. Unprefixed helpers leak into the whole Pest suite

**What's wrong.** [DiscountableLinesTest.php:11-51](../../tests/Feature/Services/Discounts/DiscountableLinesTest.php#L11-L51)
declares `fullPaymentLine()`, `prepaymentLine()`, `bundleLine()`, `contextOf()` as suite-global functions,
while the sibling new file prefixes its helpers (`tierCondition*`).

**Why it matters.** Pest loads helper functions globally; two files declaring `contextOf()` is a fatal
collision, and it fails at some unrelated future file rather than at the one that caused it.

**How it should be.** Namespace the helpers (`discountableContextOf`, …) or move them into
`tests/Support/Helpers/` (item 13 already creates that home).

**Done when.** No new bare generic function name enters the global test namespace.

### 15. `reserved_usage_count` loading copy-pasted at five call sites

**What's wrong.** `withCount`/`loadCount('heldUsages as reserved_usage_count')` appears five times
([DiscountPromotionController.php:52,72,88,108](../../app/Http/Controllers/Api/Admin/Promotion/DiscountPromotionController.php#L52) and
[DiscountPromotionStatusUpdateController.php:35](../../app/Http/Controllers/Api/Admin/Promotion/DiscountPromotionStatusUpdateController.php#L35)).

**Why it matters / how it should be.** The "reported capacity" definition now has five homes; #157 produced one
review round already because `reserved_usage_count` briefly meant the wrong scope. Put the load on the model
(e.g. `DiscountPromotion::scopeWithReservedUsage()` or a small presenter) so `held()` is chosen once.
Note the N+1 discipline stays: keep it a single `withCount`, not a per-row query.

**Done when.** The controller no longer names `heldUsages` and the payload is unchanged.

### 16. A default argument that exists only for tests

**What's wrong.** [ApplyFixedAmountOffAction.php:17-19](../../app/Services/Discounts/Cart/Actions/ApplyFixedAmountOffAction.php#L17-L19)
defaults `WeightedApportionment $apportionment = new WeightedApportionment()` so tests can call
`new ApplyFixedAmountOffAction()`; production always resolves through the container.

**Why it matters / how it should be.** `new` in a constructor default cannot be autoloaded as a service and
hides the real dependency graph. Either inject it from the tests (`new ApplyFixedAmountOffAction(new
WeightedApportionment())`) and drop the default, or make the apportionment a pure static helper like the other
value helpers. Small, but it is the kind of thing that teaches the next reader that defaults here are normal.

**Done when.** No production class carries a dependency default that exists only for tests.

---

## Part 5 — Where the spec, not the code, is wrong

### 2. Tier ladder and value conditions measure two different amounts

**What's wrong.** #155 acceptance criterion 4 says *"The ladder is measured on the same basis that value
conditions use"*. `CartValueCondition` measures `thresholdBasis($measure_whole_commitment)`
([CartValueCondition.php:30](../../app/Services/Discounts/Cart/Conditions/CartValueCondition.php#L30)) — the
customer's whole commitment, or the full-payment lines — while `CartValueTierCondition` measures
`discountableTotal()` ([CartValueTierCondition.php:48](../../app/Services/Discounts/Cart/Conditions/CartValueTierCondition.php#L48)),
i.e. the product-effective worth of only the lines the consuming action may mark down. The author recorded a
ruling on #155: same *price basis* (effective, never base), deliberately narrower *line set*, justified by user
story 3 ("the reward is not a promise the engine cannot keep").

**Why it matters.** Both readings are defensible and they produce different discounts on a cart with a deposit.
The problem is not the behaviour; it is that the code and the written criterion disagree, so the next person
either "fixes" working behaviour or stops trusting the specs.

**How it should be.** Decide explicitly, then make the artefact match:

- **Recommended:** keep the code (measuring a tier on deposits would promise a tier the markdown cannot
  deliver) and **amend #155 AC 4** to say *"measured on the same price basis as value conditions (product
  effective, never base), over the discountable line set"*. Record the ruling in the digest next to the tier
  bullet, and add a one-line comment in the condition's class docblock (already there — point the AC at it).
- Alternative: change the condition to `thresholdBasis(true)` and accept tier promises that include deposits.
  Only choose this if the product actually wants deposit-inclusive tiers, which #149's US 3 argues against.

**Done when.** The AC text and the class docblock say the same thing, and a test pins the chosen behaviour on a
cart that mixes a deposit with full-payment lines.

### 5. "Admin sees reserved capacity" is only half true

**What's wrong.** #157 AC 5 says *"Admin-facing promotion data exposes reserved capacity alongside completed
usage."* That is satisfied on the promotion resource, but the admin order creation/preview path shows no
reserved capacity, so an admin creating an order cannot see that a campaign is fully reserved.

**Why it matters.** Low: the AC as written is arguably about the promotion payload and is met there. The risk is
only that a reader expects it in the order flow.

**How it should be.** Either close it as satisfied (the promotion endpoints are the admin-facing cap surface)
and tighten the AC wording to name those endpoints, or add the figure to the admin order preview. Recommend the
first — the order preview is about the price of one cart, not about campaign budget.

**Done when.** The AC names the promotion endpoints, or the order preview shows the number.

### 17. #151 leftovers worth scheduling

**What's wrong.** #151 still holds two decided-but-unimplemented items: renaming the "add wallet credit"
action label/description so it reads as a non-withdrawable bonus (decision recorded, "optional"), and the
deactivation/expiry UX for a code applied to a cart.

**Why it matters.** Both are small, both were explicitly decided, and both are the kind of thing that quietly
becomes permanent. Item 4 above touches the same code as the deactivation UX, so do them together.

**How it should be.** One commit for the label/description rename in both locales (plus the digest if it names
the action), one commit for the deactivation UX (clear the code and/or surface the reason), then update #151 and
close it.

**Done when.** #151 has no items that are neither done nor explicitly dropped.

---

## Order of work

1. **Item 0 verification** — already merged; confirm the reward tests are green on the merged tip.
2. **Contract and doc drift (6, 7, 8)** — cheapest, no behaviour risk, and they stop the wrong contract from
   being copied into the next change. Item 8 also unblocks a trustworthy reading of the audit.
3. **Code items with tests (1, 3, 4, 17)** — each behind its own failing-first test.
4. **Spec decisions (2, 5)** — no code unless the decision goes the other way; update the issue text.
5. **Hygiene and refactors (9–16)** — mechanical, one commit each, keep them out of the behaviour commits.
6. **Digest check** — after 1–4, re-read the affected `DIGEST_*.md` sections and update them in the same
   commits (AGENTS.md digest maintenance). Items 6/7/8 are themselves digest/document work.

## Verification recipe for whatever lands

```bash
vendor/bin/sail bin pint --dirty --format agent                 # PHP style
vendor/bin/sail bin pest <the test files you changed>           # one Pest command at a time
vendor/bin/sail artisan test --compact --parallel               # full suite before finishing
```

Rules that bit this review, worth keeping:

- `.ai/rules/tests.md:9,11` — always `--parallel`, and never run two Pest commands at once; interleaving
  corrupts the `testing_test_*` databases and looks like ~149 migration failures.
- Pest's `--parallel` accepts one path, not a file list; scope by directory or fall back to a single run.
- Response fixtures under `resources/responses/**` are contracts, not decoration: after changing an endpoint's
  payload, regenerate or re-verify the fixture in the same commit.

## Appendix — things that look wrong but are not

- **The bundle/prepayment guard exists twice** (`discountableItems()` filters, and
  `CalculatedOrderItemData::applyDiscount()` refuses). That is deliberate belt-and-braces, ratified in
  `DIGEST_CORE_LOGIC.md`: the set prevents the mistake, the guard makes it impossible.
- **No concurrency test for cap reservation.** Spec #150 decided bounded overshoot (option a); a concurrency
  test would pin behaviour the product declined to guarantee. Revisit only if a cap becomes a hard commitment.
- **The backfill migration was added then deleted**, and that is correct: no production data predates
  `discount_promotion_usages`, so there was nothing to migrate.
- **`active()` vs `held()`** — two scopes on purpose. `active()` decides caps (settled rows count forever,
  fresh holds count, stale holds do not); `held()` is the admin reporting number (unsettled holds only), so
  completed usage is not reported twice alongside `total_usage_count`.

---

## Implementation log (2026-10-14)

Every item below landed on `refactor/discount-system`, grouped by concern (the test-suite hygiene
items share one commit), `vendor/bin/sail bin pint --dirty` clean and the focused suites green after
each.

| # | Outcome | Commit |
| --- | --- | --- |
| 0 | Verified: `RewardGrantingTest` 4 passed on the merged tip | `d9e20cb9` |
| 1 | **Not implemented — deferred by decision.** The fields stay accepted and unenforced | — |
| 2 | **Spec amended, not code.** #155 AC 4 now reads "same *price basis* as value conditions — product-effective, never base prices — over the *discountable line set*". The ladder keeps measuring `discountableTotal()` | issue body, 2026-10-14 |
| 3 | Snapshot `effective_subtotal_discountable_items` added; `thresholdBasis(false)` reads it, the legacy subtotals stay audit-only. Decision taken: the full-payment side counts **only the discountable lines**, so a bundle no longer carries a cart over a threshold the markdown cannot reach | `82ba23a1` |
| 4 | **Cap premise disproved, notice shipped instead.** See "Deviations" below | `fb84423d` |
| 5 | **Spec amended, not code.** #157 AC 5 now names the promotion endpoints as the reserved-capacity surface | issue body, 2026-10-14 |
| 6 | All five promotion response fixtures regenerated through `DiscountPromotionData` | `54c15c1c` |
| 7 | Digest names `TierData`; swept for other pre-#155 survivors (none left) | `dbecfaf6` |
| 8 | `docs/Architecture/Discount-Limit-Scenario-Audit.md` tracked and rewritten: §9 is now a per-finding status with the commit that closed it, plus the two spec amendments and what is explicitly deferred | this commit |
| 9 | **Not implemented — the proposal cannot work.** See "Deviations" below | — |
| 10 | `DiscountableLinesTest` and `CartValueTierConditionTest` moved from `Feature` to `Integration` | `d7422a6c` |
| 11 | The cart-value knob's copy is asserted through its translation instead of a literal string in both locales | `d7422a6c` |
| 12 | `PromotionCapacity::exhausted()` is the only place a count is compared to a cap | `0ea3b5d4` |
| 13 | `tests/Support/Helpers/DiscountHelpers.php`: one line builder set, one context builder, one reservation-slot builder (single and count) | `d7422a6c` |
| 14 | Every helper is `discount`-prefixed; the bare `fullPaymentLine()`/`contextOf()` globals are gone | `d7422a6c` |
| 15 | `DiscountPromotion::withReservedUsage()` scope + `loadReservedUsage()`; no controller names `heldUsages` any more | `abea6918` |
| 16 | The test-only constructor default is gone; tests inject `WeightedApportionment` | `d7422a6c` |
| 17 | Wallet-credit label renamed in both locales (+ published metadata examples); the deactivation UX is the item-4 notice | `111ff514`, `fb84423d` |
| 18 | **New finding, approved with the product owner during implementation:** the operator value `DiscountMetadataService::getOperators()` publishes (`greater_than_or_equal`) could not be cast by `CartValueConditionConfigData`, so a promotion configured from the published metadata threw `CannotCastEnum` on every cart it reached. `MathOperatorEnum::fromAlias()` + `MathOperatorCast` accept both spellings, and validation accepts both | `bad8b1ec` |

### Deviations from this plan, and why

- **Item 4 (coupon deactivated after being applied).** The "escapes its own cap" half does not
  survive checking. Both finders require an **active** coupon row for the typed code — that
  `where('is_active', true)` is in the finder query, not only in the cap lookup — so a deactivated
  code never reaches `isCouponCapFullyReserved()`; the promotion is simply not returned, and
  `findPromotionByCoupon()` returns null too. Removing the filter from the cap accounting would
  therefore change no cap outcome, while it *would* newly drop an automatic promotion
  (`requires_coupon = false`) whose typed code happens to be inactive with a full cap. The only
  reachable defect was the silent drop, so the fix is presentation: `CartData::$applied_coupon_error`
  explains the missing discount on every cart read and never blocks checkout.
- **Item 9 (move the registry sweep to `tests/Architecture`).** That suite is deliberately not in
  `uses(Tests\TestCase::class)->in(...)` in `tests/Pest.php`, so it gets no application container
  and no database — an AST-only suite by construction. A sweep that resolves
  `DiscountHandlerRegistry` and creates `ProductDeliveryOption` factories cannot live there without
  giving the suite an app boot, which is exactly what `.ai/rules/tests.md:43` forbids. The file is
  behavioural, and #152 mandated it as one registry-driven test, so it stays in `Feature`; its
  fixture builders moved to the shared helper (item 13) and its deposit-only context is now
  `discountDepositOnlyContext()`.
- **Item 1 (product-specific usage limits).** Deliberately left as it is, per the product owner:
  the DTO keeps accepting the fields and the projection engine keeps ignoring them. Recorded as
  deferred in the audit's §9.4 and on tracker #151.
- **Item 3, one decision inside it.** The plan's snapshot could have preserved today's bundle
  behaviour (all full-payment lines) or mirrored `discountableTotal()`. The owner chose the
  discountable set, so a bundle-heavy cart can now fall below a threshold it reached before; that
  is the "never promise a markdown the engine cannot keep" rule from #149, applied to thresholds.

### Found while reviewing this work

- **Stacked promotions erased the coupon code the order consumed.** `OrderCalculationService`
  assigned `OrderContextData::$triggered_by_coupon_code` inside the promotion loop, so a later
  *automatic* promotion reset it to null. Two consequences, one of them pre-existing and worse than
  the notice this branch added: `orders.applied_coupon_code` was written as null, so
  `IncrementDiscountUsageCountsAction` — which resolves the coupon to increment from that column —
  never moved the coupon's `usage_count`; and `CartData::$applied_coupon_error` reported "not
  applied" while the discount *was* applied. The field now accumulates: it holds one promotion's
  attribution while that promotion's actions run (so handlers and the audit entry stay correct) and
  keeps the last consumed code afterwards. Covered by a test with the coupon promotion first and
  an automatic promotion after it.
- Review also flagged that the discountable-line predicate had grown a third and fourth copy. It is
  now `CalculatedOrderItemData::isDiscountable()`, shared by `applyDiscount()`,
  `OrderContextData::discountableItems()`, the context-build snapshot and the test helper.
- Item 18 was an unrequested finding, but the fix was put to the product owner during
  implementation and approved (accept both operator spellings rather than change the metadata).
- **Two more pre-existing bugs surfaced while answering "is anything left to fix?"**, both put to the
  owner and approved, both fixed on this branch:
  - **A config key the validation does not require but the DTO does.**
    `CartValueConditionConfigData` required `measure_whole_commitment` while its validation rule was
    only `['boolean']`, so a rule that omits the key — including any row stored before the #149
    rename, which is exactly what this workspace's database still holds — threw `CannotCreateData`
    from `promotionConditionsPass()` and 500'd every cart calculation that reached the promotion.
    The key now defaults to `false` (the old `include_prepayments = false` meaning) and the admin
    metadata reports it optional.
  - **Reward-only promotions consumed no usage budget.** `RecordPromotionUsageAction` and
    `IncrementDiscountUsageCountsAction` both iterated `applied_cart_discounts_json`, which only
    gains an entry when the actions changed a price, so a promotion whose only action granted wallet
    or gift credit never created a usage row and never moved a counter. Probe before the fix: with
    `usage_limit_total = 1`, `usage_limit_per_customer = 1` and the coupon's `usage_limit = 1`, the
    first order stored the code and the reward intent but created 0 usage rows and left the counter
    at 0, and a second order was granted another 5,000. `Order::usedPromotionIds()` now unions the
    discount log with `reward_intents_json` and both actions read it; the same probe now shows one
    usage row (carrying the coupon id) and a second order with no code and no reward intent.
