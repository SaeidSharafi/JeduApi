# Discount limit & eligibility scenario audit

Status: tracked in git as of `refactor/discount-system`. **Section 9 is the current status of every
finding, with the commit or spec that closed it — read it first.** Sections 1–8 are the original
record of what was wrong when this audit was written, kept for the scenario matrix and the evidence;
where they disagree with section 9, section 9 wins.
Trigger: a customer who had already consumed an **automatic** cart promotion
(`requires_coupon = false`) was refused at checkout with a *coupon* error instead of
simply buying without the discount. This document maps the whole limit/eligibility
surface, every combination, and the issues found, before any fix.

Reported symptom → scenario mapping: the customer bought once under an automatic
(`requires_coupon = false`) promotion with `usage_limit_per_customer = 1`, the slot was consumed
(`completed`), and the next eligible cart was refused instead of being sold without the discount
= **A2 + B3**, blocked by `ValidatePromotionPerCustomerLimitAction` at `CreateOrderAction.php:74`.

Evidence base: code at the cited `path:line`, `docs/adr/0003-coupon-usage-state-machine.md`
("ADR-0003"), `docs/Architecture/Discount-Promotion-Engine.md`, and the existing tests
(`tests/Feature/Api/V1/Shop/Sale/PerCustomerCouponLimitTest.php`,
`CouponUsageCounterTest.php`).

---

## 1. How the system actually decides a discount

### 1.1 Two engines, two rule sets

| Engine | Promotion type | Evaluated when | Where the result lives | Coupons allowed |
| --- | --- | --- | --- | --- |
| Cart/checkout (`PromotionService` → `OrderCalculationService`) | `cart_checkout` | Every cart view and every checkout, in-memory, side-effect free (`Discount-Promotion-Engine.md:53`) | `OrderContextData.applied_cart_discounts` → order columns + `applied_cart_discounts_json` | Yes |
| Product projection (`ProductDiscountIndexer` → `ProductDiscountPriceCalculator`) | `product_specific` | On promotion/catalog write, materialised | `product_delivery_option_discount_prices` (read through `ProductDeliveryOption::discountPrice`) | No — DTO validation prohibits coupons (`DiscountPromotionCreateData.php:67`) |

Only `cart_checkout` promotions can ever appear in `applied_cart_discounts`
(`OrderCalculationService.php:87-100`).

### 1.2 Cart-promotion modes

`PromotionService::findAllApplicableCartPromotions()` (`app/Services/Discounts/PromotionService.php:73-107`) selects a promotion when **all** of these hold:

1. `type = cart_checkout`, `is_active = true`, inside `starts_at`/`ends_at` (`:77-84`).
2. `usage_limit_total` is null **or** `total_usage_count < usage_limit_total` (`:85-88`).
3. Either `requires_coupon = false` (automatic), **or** some active coupon whose `code` equals the cart's `applied_coupon_code` and whose `usage_count < usage_limit` (`:89-102`).
4. Per-customer allowance is **not** consulted here at all — there is no customer in the signature.
5. Every `condition` rule then passes (`promotionConditionsPass`, `:185-212`).

### 1.3 The four limit kinds

| Limit | Column | Read/checked at | Counter/row incremented | Enforced as |
| --- | --- | --- | --- | --- |
| Promotion total cap | `discount_promotions.usage_limit_total` | `PromotionService.php:85-88` (cart) and `:48-51` (coupon apply) | `total_usage_count` on order **completion** (`IncrementDiscountUsageCountsAction`) | Promotion silently drops out of the calculation |
| Promotion per-customer cap | `discount_promotions.usage_limit_per_customer` | `ValidatePromotionPerCustomerLimitAction.php:46-61` at checkout only | `discount_promotion_usages` row at order creation (`RecordPromotionUsageAction`) | **Hard `ValidationException` on key `coupon`** |
| Coupon total cap | `discount_coupons.usage_limit` | `PromotionService.php:96-99` (cart) and `:55-58` (coupon apply) | `usage_count` on completion | Promotion silently drops out / code not found |
| Product-projection caps | same promotion columns | **nowhere** | **nothing** | **Ignored entirely** |

### 1.4 Usage-slot state machine (ADR-0003)

`discount_promotion_usages` (migration `2026_08_19_000001_create_discount_promotion_usages_table.php`; one row per promotion per order, `unique(discount_promotion_id, order_id)`).

| Order status transition | Slot row | `total_usage_count` / coupon `usage_count` |
| --- | --- | --- |
| created `pending` | created (`CreateOrderAction.php:211` → `RecordPromotionUsageAction`) | untouched |
| `pending`/`processing` | kept (reserves) | untouched |
| → `completed` | kept (consumes) | incremented once, guarded by `discount_usage_incremented_at` (`OrderStatusService.php:123-125`) |
| → `cancelled` / `failed` | **deleted** (`OrderStatusService.php:128-132`, `ReleasePromotionUsageSlotsAction.php:21-23`) | untouched |
| → `refunded` / `partially_refunded` | kept — refunds never restore quota (asserted by `PerCustomerCouponLimitTest` "a refunded order still consumes the slot") | untouched |

### 1.5 Shop checkout pipeline (order matters)

`CreateOrderFromCartAction::buildOrderCreateData()` (`:358-388`) reads
`applied_coupon_code` from the cart → `CreateOrderAction::handle()`:

1. `:52` calculate context (preview-grade, no limit check).
2. `:53` open transaction, lock user.
3. `:69` duplicate-purchase eligibility.
4. `:70` **recalculate** context under lock.
5. `:74` `ValidatePromotionPerCustomerLimitAction` → may throw for **any** applied promotion.
6. `:107` reserve capacity, `:175` create order, `:211` record usage slots.
7. `CreateOrderFromCartAction.php:99` deletes the cart.

Preview surfaces that **skip** step 5: `CartService::buildCartDataWithTotals()`
(`app/Services/CartService.php:386-437`), `CartService::applyCoupon()` (`:167-212`), and the
admin `OrderCalculationController` (calls `OrderCalculationService::calculate()` directly).

---

## 2. Scenario catalogue

Legend — Expected: `S` = sell at full price / skip the promo, `D` = apply discount,
`E` = explicit error, `?` = product decision needed. Verdict: ✅ matches intent,
⚠️ inconsistent, ❌ wrong.

### A. Promotion mode × coupon state

| # | Setup | Expected | Actual | Verdict |
| --- | --- | --- | --- | --- |
| A1 | automatic promo, conditions pass, no usage history | D | D | ✅ |
| A2 | automatic promo, `usage_limit_per_customer` reached | S | **E** (`coupon` key, "limit reached") | ❌ **reported bug** (I1) |
| A3 | automatic promo, `usage_limit_total` reached | S | S — filtered by `total_usage_count` | ✅ |
| A4 | coupon promo, code applied, first use | D | D | ✅ |
| A5 | coupon promo, code applied, per-customer limit reached | E (existing tests) or S? | E | ⚠️ see I2/I3 |
| A6 | coupon promo, no code applied | S | S | ✅ |
| A7 | coupon promo, code of another promotion applied | S | S (`:92-101` requires the code on that promotion) | ✅ |
| A8 | coupon promo, code applied, coupon `usage_limit` reached | S (code unusable) | S, but `applyCoupon` already rejected it at `:55-58` | ✅ |
| A9 | code applied, promotion becomes inactive/expired before checkout | S | S, order gets no discount and `applied_coupon_code = null` | ⚠️ silent drop (I11) |
| A10 | exhausted automatic promo + a valid second promo/coupon | D for the second | **E** — the loop throws before/regardless of the second | ❌ (I2) |
| A11 | exhausted promo with `stop_processing_subsequent_rules = true` | other promos still considered | others never applied, then E | ❌ (I2) |

### B. Per-customer limit × order history

| # | Customer history for the promo | Expected | Actual | Verdict |
| --- | --- | --- | --- | --- |
| B1 | no orders | D | D | ✅ |
| B2 | 1 pending order holding the slot, limit 1 | E naming the pending order | E naming the order | ✅ for coupons, ❌ for automatic |
| B3 | 1 completed order, limit 1 | S for automatic / E for coupon | E for both | ❌ (I1) |
| B4 | 1 cancelled order, limit 1 | D | D (slot deleted) | ✅ |
| B5 | 1 failed order, limit 1 | D | D | ✅ |
| B6 | 1 refunded order, limit 1 | ? (quota kept by design) | E | ? (I7) |
| B7 | 1 partially refunded order, limit 1 | ? | counts as active → E | ? (I7) |
| B8 | limit `null` | D every time | D (early `continue` at `:46-48`) | ✅ |
| B9 | 2 usage rows but limit 2 | D once more | D | ✅ |
| B10 | limit pooled across two codes of one promotion | second code blocked | blocked (`RecordPromotionUsageAction` stores promotion, not code) | ✅ by design |
| B11 | pending order with only failed payment attempts, abandoned | slot released eventually | **never** — `CancelAbandonedOrdersCommand.php:61-63` skips orders that have any payment row | ❌ (I8) |
| B12 | pending order, abandoned-cancel command not scheduled | slot released at timeout | held indefinitely | ⚠️ (I8) |

### C. Cart content combinations

| # | Cart content + coupon promo | Expected | Actual | Verdict |
| --- | --- | --- | --- | --- |
| C1 | full-payment lines only | D on lines | D | ✅ |
| C2 | mixed full + prepayment | D on full lines only | D, prepayment skipped (`ApplyPercentageDiscountToItemsAction.php:41-42`) | ✅ |
| C3 | prepayment lines only, coupon applied | either D or clear feedback | no discount, `applied_cart_discounts = []`, **no usage row**, code still stored | ⚠️ (I12) |
| C4 | bundle lines only, coupon applied | same as C3 (bundles never discounted) | no discount, no usage row | ⚠️ (I12) |
| C5 | mixed bundle + standalone, coupon applied | D on standalone only | D | ✅ |
| C6 | flat-amount coupon (`apply_fixed_amount_off`) on a product-discounted cart | full flat amount | **less than the flat amount** — weight is `subtotal_all_items` while the item total is already marked down (`ApplyFixedAmountOffAction.php:29-49`) | ⚠️ (I13) |

### D. Global caps & concurrency

| # | Scenario | Expected | Actual | Verdict |
| --- | --- | --- | --- | --- |
| D1 | `usage_limit_total` nearly reached, many pending orders | cap respected | each pending order passes; counter only rises at completion → **oversubscription** | ❌ (I6) |
| D2 | coupon `usage_limit` nearly reached, many pending orders | cap respected | same oversubscription | ❌ (I6) |
| D3 | two simultaneous checkouts, per-customer limit 1 | one wins | promotion row `lockForUpdate` + usage count → one wins | ✅ |
| D4 | promo hits `usage_limit_total` between cart view and checkout | order without discount, no error | drops silently | ⚠️ asymmetric with per-customer error (I6) |

### E. Channels

| # | Channel | Expected | Actual | Verdict |
| --- | --- | --- | --- | --- |
| E1 | shop checkout | as A/B | as A/B | — |
| E2 | admin order creation | same enforcement | same hard error, admin cannot sell at full price either (test asserts) | ⚠️ (I10) |
| E3 | admin order **calculation preview** | show what checkout will do | shows the discount even when checkout will reject | ❌ (I3) |
| E4 | cart view / `applyCoupon` | never show a discount that checkout refuses | shows it, `applyCoupon` returns 200 | ❌ (I3) |
| E5 | guest / `customer_id = null` | limit enforcement N/A | early return `:30-33`, unreachable because `customer_id` is required | ℹ️ (I14) |

### F. Lifecycle & data

| # | Scenario | Expected | Actual | Verdict |
| --- | --- | --- | --- | --- |
| F1 | promotion deleted with usage rows | rows cleaned | `cascadeOnDelete` | ✅ |
| F2 | coupon deleted, slot already consumed | slot still consumed | `nullOnDelete` comment documents it | ✅ |
| F3 | double completion | counters increment once | `discount_usage_incremented_at` guard | ✅ |
| F4 | `product_specific` promo with per-customer/total limits | enforced or rejected at write | **silently ignored** (indexer filters only active/date, `ProductDiscountIndexer.php:240-253`) | ❌ (I5) |
| F5 | `product_specific` usage counters | incremented | never | ❌ (I5) |
| F6 | promotion edited after orders exist | snapshots unchanged | order snapshots untouched | ✅ |

---

## 3. Issues, ranked

**I1 — Exhausted *automatic* promotion blocks the whole purchase (the reported bug).**
`ValidatePromotionPerCustomerLimitAction` loops `context->applied_cart_discounts`
(`:35-81`) and never looks at `requires_coupon`. An automatic discount that the customer
never asked for, and whose allowance is used up, throws `coupon_usage_limit_reached`
(`:72-80`) → `422`, no sale. Expectation: skip the promotion, sell at full price.
Evidence: `CreateOrderAction.php:74`, `ValidatePromotionPerCustomerLimitAction.php:35-81`,
`OrderCalculationService.php:87-100`.
Severity: high (lost revenue, reported).

**I2 — One exhausted promotion blocks every other valid promotion and can suppress them silently.**
The loop throws on the first exhausted entry, so a still-usable coupon/automatic promo in the
same cart cannot be used at all. If the exhausted promotion has
`stop_processing_subsequent_rules = true`, `OrderCalculationService.php:48-50` has already
skipped the others before the error is raised.
Severity: high.

**I3 — Cart preview and `applyCoupon` disagree with checkout.**
Neither `CartService::applyCoupon()` nor `buildCartDataWithTotals()` receives a customer for
per-customer filtering, so the cart displays the discount and the code as applied; checkout then
rejects with `422`. The admin calculation preview has the same divergence. This is the
"discount shown but I can't buy" experience.
Evidence: `CartService.php:167-212`, `:386-437`, `OrderCalculationController` → `calculate()`.
Severity: high.

**I4 — Automatic promotions consume a per-customer allowance at all.**
`RecordPromotionUsageAction` and `IncrementDiscountUsageCountsAction` treat automatic and
coupon promotions identically. Even if I1 is fixed by filtering, the product decision "does an
automatic discount burn a per-customer slot?" must be answered explicitly.
Severity: medium-high (drives the fix shape).

**I5 — `product_specific` promotions ignore every usage limit.**
They never enter `applied_cart_discounts`, so no slot is recorded, no per-customer check runs,
and `total_usage_count` never increments. The admin DTO still accepts
`usage_limit_total`/`usage_limit_per_customer` for them
(`DiscountPromotionCreateData.php:62`), which are then silently meaningless.
Severity: medium.

**I6 — Global caps are not enforced atomically, and drop silently.**
`usage_limit_total`/coupon `usage_limit` are evaluated from counters that only change on
completion, so N pending orders can oversubscribe a cap (only the per-customer path is locked).
Separately, a promotion that loses eligibility silently disappears from the calculation, while
the per-customer case hard-fails — two different failure modes for the same class of limit.
Evidence: `PromotionService.php:85-99`, `IncrementDiscountUsageCountsAction.php:28-60`.
Severity: medium.

**I7 — Refunds keep the slot; partial refunds are ambiguous.**
ADR-0003 and the tests make "refunded consumes" deliberate, but `partially_refunded` also counts
(`ValidatePromotionPerCustomerLimitAction.php:53-56` excludes only cancelled/failed). If the
intent is "refund returns quota", both the release trigger and the count filter change.
Severity: medium (product decision).

**I8 — Abandoned pending orders hold the slot, in one case forever.**
`CancelAbandonedOrdersCommand` only cancels pending orders with **no payment rows at all**
(`:61-63`), so a pending order with a failed attempt holds the coupon slot indefinitely.
Severity: medium.

**I9 — The error is a *coupon* error even when no coupon is involved.**
Key `coupon`, message `coupon_usage_limit_reached`, no promotion name → the customer cannot tell
what blocked them. Evidence: `ValidatePromotionPerCustomerLimitAction.php:72-80`.
Severity: low-medium.

**I10 — Admin order creation is blocked by the same rule.**
An admin cannot create an order at full price for a customer whose automatic-promo allowance is
exhausted (test "admin order creation enforces the per-customer limit" asserts the 422).
Severity: low-medium.

**I11 — Promotion deactivation/expiry between apply and checkout silently drops the code.**
No feedback; `orders.applied_coupon_code` becomes `null` because
`triggered_by_coupon_code` is only set by an applied promotion (`OrderCalculationService.php:43-45`).
Severity: low.

**I12 — Prepayment-only or bundle-only carts keep the code but consume nothing.**
`applied_cart_discounts` stays empty, so no slot and no limit interaction; the code remains on
the cart/order. Consistent for usage, confusing for the user.
Severity: low.

**I13 — Flat-amount coupons under-deliver on product-discounted carts.**
`ApplyFixedAmountOffAction` distributes `min(amount, totalWeight)` by `total / subtotal_all_items`,
where `subtotal_all_items` is the pre-product-discount value; the cart receives less than the
advertised flat amount (observed: 380,000 of an advertised 500,000).
Severity: medium (money, customer-visible).

**I14 — The "no customer" guard is dead code today (not a guest policy).**
`ValidatePromotionPerCustomerLimitAction.php:30-33` returns early when `customer_id` is null, but
`OrderCreateData.php:29` requires `customer_id` (`required|integer|exists:users,id`) and
`PromotionService.php:118` resolves it, so no validated entry point can reach the guard. Keep it
as a defensive guard, but do not treat it as a guest-checkout policy:
`discount_promotion_usages.customer_id` is `NOT NULL`.
Severity: informational.

**I15 — Admin `PUT /admin/orders/{order}` to COMPLETED never increments counters.**
`UpdateOrderAction` only releases slots for CANCELLED/FAILED (`:37-41`) and has no
`IncrementDiscountUsageCountsAction`; it writes the status directly (`:31`) and dispatches
`OrderStatusUpdatedEvent` (`:57`). An order therefore flows PENDING → COMPLETED without
`total_usage_count`/coupon `usage_count` moving and without `discount_usage_incremented_at`
being set, so global caps silently under-count. No test covers this path.
Evidence: `app/Actions/Admin/Order/UpdateOrderAction.php:27-61`.
Severity: high (silent counter drift).

**I16 — Admin `POST /admin/orders` with `status = completed` skips the counters entirely.**
`OrderCreateData.status` accepts any `OrderStatusEnum` (`OrderCreateData.php:28`), and
`CreateOrderAction` writes it verbatim (`:177`) while recording usage rows (`:211`). An order
created directly as COMPLETED never passes through `OrderStatusService::updateParentOrderStatus`,
so the "completed ⇒ increment" rule at `OrderStatusService.php:123-125` never fires.
Severity: medium-high (the same drift through a second door).

**I17 — Prepayment lines are treated inconsistently across cart actions (new finding).**
`ApplyPercentageDiscountToItemsAction` skips `PRE_PAYMENT` lines (`:41-42`), but
`ApplyFixedAmountOffAction` and `ApplyTieredPercentageOffAction` skip only bundle lines, so both
*do* mark prepayment lines down. `CreateOrderAction` then writes zero discount into
`pricing_metadata` for those same lines while `order_items.total` and `orders.grand_total` already
carry the reduction, so the billable amount and the audit metadata disagree.
Evidence: `app/Services/Discounts/Cart/Actions/ApplyFixedAmountOffAction.php`,
`ApplyTieredPercentageOffAction.php:39-40`,
`ApplyPercentageDiscountToItemsAction.php:41-42`, `CreateOrderAction.php:124-168`.
Severity: medium (money + audit mismatch); needs a product decision — skip prepayment everywhere,
or allow it everywhere and fix the metadata.

**I18 — Reward actions write real wallet transactions during calculation (preview side effects, no idempotency) — CONFIRMED by probe.**
`AddWalletCreditAction.php:56` and `AddGiftCreditAction.php:62` call `RecordWalletTransactionAction`
from inside `OrderCalculationService::calculate()`, passing no `idempotency_key`; separately,
`OrderContextData::$order_id` is never assigned anywhere, so `metadata.order_id` is always `null`.
`calculate()` runs on every cart read (`CartService::buildCartDataWithTotals`) and three times per
shop checkout (`CreateOrderFromCartAction.php:127` pre-flight plus `CreateOrderAction.php:52` and
`:70`). Probe result on the current code: two `calculate()` calls on one cart produced two
`wallet_transactions` rows with the same promotion, `idempotency_key = NULL`,
`metadata.order_id = null`, and moved `wallets.gift_balance` from 0 to 200,000. This contradicts
`Discount-Promotion-Engine.md:53` ("Preview calculations must be side-effect free").
Secondary oddity: both actions use `TransactionTypeEnum::BONUS`/`GIFT`, which credit
`gift_balance` (non-withdrawable), so "add wallet credit" effectively grants gift credit.
Severity: high (money creation on a GET); fix shape pending (record intent + write once at order
creation with an idempotency key, or write at payment completion).

---

## 4. Decisions needed before fixing

### Decided in review (design agreed, not implemented)

Published specs (Forgejo `kuro/Jedu-api`), all three now `ready-for-agent`: **#148** grant
promotion rewards at order completion, **#149** never mark down prepayment lines + unify the cart
measurement basis, **#150** reserve promotion/coupon caps at placement + expire stale holds.
**#151** is the tracker for the remaining findings and decided behaviours. Spec **#149** is broken
into four tracer tickets: **#152** never mark down a prepayment line, **#153** measure cart
thresholds on one price basis, **#154** flat-amount coupons grant the advertised amount (blocked by
#152), **#155** tier selection becomes an eligibility condition (blocked by #152 and #153). Spec
**#150** is broken into **#156** expire stale promotion reservations and **#157** count reserved
capacity against promotion and coupon caps (blocked by #156). Frontier: #152, #153, #156.

- **Prepayment (S1, #149)** — cart promotions never mark down a `PRE_PAYMENT` line, enforced **centrally**
  so every action inherits one rule instead of each action filtering for itself (the copy-paste
  that produced I17). `include_prepayments` is re-scoped and renamed as a **measurement-only**
  option on conditions ("which amount counts toward this threshold"); it must never switch what
  gets discounted. Discounting deposits, if ever wanted, needs derived deposit arithmetic
  (S2/S3), because a deposit is currently an absolute amount that `ApproveOrderAction` demands in
  full even when the customer was charged a discounted total.
- **Global caps (I6, #150)** — reserve at order placement (Bagisto model): eligibility counts active
  `discount_promotion_usages` rows per promotion/coupon; `total_usage_count`/`usage_count` stay as
  the completed-orders reporting metric. Concurrency decided: **accept a bounded overshoot** (a cap
  may be exceeded by the number of genuinely simultaneous checkouts, documented as a budget target);
  revisit with an atomic counter only if a cap becomes a hard commitment.
- **Stale reservations (I8, #150)** — a TTL on the reservation itself, independent of order
  cancellation, releases old holds (failed-attempt pending orders currently hold forever).
- **I13** — deferred until S1 exists, so the flat-amount fix apportions over the shared
  "discountable lines" set rather than introducing another line filter.
- **Tier selection (Q1, #149)** — the ladder stays one campaign; a condition owns it, measures on the
  unified basis, selects the highest satisfied tier and **publishes that selection** on the context;
  a config-free action consumes it and applies the percentage to discountable lines. Conditions may
  publish selections but never write prices. Modelling each tier as its own promotion was considered
  and rejected: it fragments campaign identity/budget and lets overlapping ranges stack tiers.
- **Enforcement (Q2, #149)** — context accessor for the discountable lines **plus** an item-level
  `applyDiscount()` guard that no-ops on bundle/prepayment lines, so a mis-filtered loop cannot
  mark a deposit down.
- **Measurement basis (Q3, #149)** — add one consistent-basis number (effective prices, all lines) for
  condition measurement and point `cart_value_over` at it, so the flag only selects the *set*;
  `subtotal_all_items` stays base-priced because `orders.full_value_grand_total`/`balance_due`
  depend on it.
- **Wallet credit destination (#151)** — agreed as intended: a promotion's "add wallet credit" is a
  non-withdrawable gift credit. Follow-up only: rename the action label/description so admins are
  not misled; not a money bug.
- **I18** — verified by probe (two calculations produced two wallet transactions, null idempotency
  key and null order reference); decided: grant at payment completion, so unpaid orders earn
  nothing. Specced in #148.
- **Open** — whether automatic promotions must serialize their reservation check (a promotion-row
  lock at checkout) or may overshoot the last slot under concurrency.

1. **Automatic promotions and per-customer limits**: should an automatic promotion consume the
   allowance at all? (Recommended: it may, but exhaustion must *filter it out*, never block the sale.)
2. **Exhausted coupon promotion**: hard error (current, tested) or silently drop the discount?
   If hard error, it should surface at `applyCoupon`/cart preview, not at checkout.
3. **Refund**: restore the per-customer slot or keep it consumed (current)? Partial refunds?
4. **Global caps**: enforce at reservation (usage-row style) or accept oversubscription?
5. **`product_specific` limits**: enforce them in the projection engine, or reject the fields in
   the admin DTO?
6. **Flat-amount coupons**: is `apply_fixed_amount_off` supposed to guarantee the advertised amount
   on discounted carts?

## 5. Fix directions (not implemented)

- Introduce a single **eligibility filter** (per promotion: active/window, global caps,
  coupon association, **per-customer usage**) that both preview and checkout call, so a
  promotion is either applicable everywhere or invisible everywhere. `findAllApplicableCartPromotions`
  needs the customer id for this.
- Keep the hard `ValidationException` **only** for the coupon the customer explicitly applied
  (code present on the cart and owning an exhausted promotion), and move that check to
  `applyCoupon` so the cart never shows an unusable code.
- For automatic promotions, let the filter drop them and continue the sale.
- Give the error its own key/message naming the promotion (not `coupon`) if a hard error remains.
- Route every status write through `OrderStatusService` (or call the increment action from
  `UpdateOrderAction`, and from `CreateOrderAction` when the created status is `completed`) so
  I15/I16 close, with the missing tests.
- Decide and implement I5, I6, I7, I8, I13 with their own changes/tests; none require this
  filter to land first.

## 6. Test plan for the fix

Must be red before, green after (seam: shop checkout HTTP + `CreateOrderAction`):

1. Automatic promo with `usage_limit_per_customer = 1`, first order completed →
   second checkout **succeeds** and is priced without the discount.
2. Second checkout line totals/`applied_cart_discounts` contain no trace of the exhausted promo.
3. Exhausted automatic promo **plus** a valid coupon promo in the same cart → sale succeeds and
   the coupon discount still applies.
4. Exhausted coupon promo (customer applied the code) → still an explicit `422` (preserve
   `PerCustomerCouponLimitTest`), and the cart view never showed the discount.
5. Cart view after exhaustion shows no discount for an automatic promo.
6. Admin calculation preview matches what checkout will produce.
7. Admin `PUT /admin/orders/{order}` → `completed` increments both counters exactly once (I15).
8. Admin `POST /admin/orders` with `status = completed` increments both counters exactly once (I16).

---

## 7. Reference: the two blocking messages

Both are keyed under `messages.checkout` and surface as HTTP 422 `errors.coupon.0`.

| Key | en | fa |
| --- | --- | --- |
| `coupon_usage_limit_reached` | "You have reached the usage limit for this discount. It can no longer be applied to your orders." | «شما به سقف استفاده از این کد تخفیف رسیده‌اید و امکان اعمال آن روی سفارش‌های شما وجود ندارد.» |
| `coupon_usage_pending_order_blocked` | "A pending order (:order_id) is currently holding your slot for this discount. Complete or cancel that order first." | «سفارش در انتظار پرداخت «:order_id» در حال حاضر سهمیه استفاده شما از این کد تخفیف را نگه داشته است. ابتدا آن سفارش را تکمیل یا لغو کنید.» |

Files: `lang/en/messages.php:494-495`, `lang/fa/messages.php:487-488`. The wording is
coupon-specific in both locales, which is why an exhausted *automatic* promotion reads as a
coupon problem to the customer (I9).

## 8. Known test-coverage gaps (relevant to any fix)

- Admin `PUT /admin/orders/{order}` → COMPLETED counter increment: **untested** (I15).
- Admin `POST /admin/orders` with `status = completed`: **untested** (I16).
- `CancelAbandonedOrdersCommand` never asserts usage-slot release (I8).
- Refund paths (`RefundOrderAction`, `UpdateRefundStatusAction`, `CreateRefundAction`,
  `RefundBundlePurchaseAction`) assert no usage-row behaviour (I7).
- `partially_refunded` has no usage-row assertion (I7).
- No concurrency test for the `lockForUpdate()` serialization in
  `ValidatePromotionPerCustomerLimitAction.php:42-45` (D3).
- `coupon_usage_pending_order_blocked` is only asserted with `toContain(increment_id)`, never as
  an exact string.
- The stored `discount_coupon_id` on a usage row is never asserted.
- `discount_promotion_usages.customer_id` is `NOT NULL`, and `DiscountPromotionUsage` declares
  no casts (unlike `DiscountCoupon`), so usage rows carry uncast timestamps; no test pins either.

---

## 9. Implementation addendum — current status of every finding

Every finding below is either closed, explicitly decided, or explicitly deferred. The commit
column is the fastest way to verify a claim: `git show <commit>`.

### 9.1 Round 1 — eligibility, counters, admin paths (on `main`)

Decisions taken: automatic promotions keep consuming the per-customer allowance, but exhaustion
only *filters them out*; an explicitly supplied coupon keeps an explicit error and is now rejected
in the cart before the code is ever stored.

| Change | Commit | Where |
| --- | --- | --- |
| New single owner of the allowance rule: `filterExhausted()` + `blockingMessage()` | `d6ae4109` | `app/Actions/Admin/Discounts/ResolvePerCustomerPromotionEligibilityAction.php` |
| `active()` scope = usage rows whose order is not cancelled/failed | `d6ae4109` | `app/Models/DiscountPromotionUsage.php` |
| Promotion finder takes `?int $customerId` and drops exhausted promotions | `d6ae4109` | `app/Services/Discounts/PromotionService.php` |
| `calculate()` passes `$data->customer_id`, so cart preview and checkout agree | `d6ae4109` | `app/Services/Discounts/OrderCalculationService.php` |
| `applyCoupon()` rejects an exhausted code with a `coupon_code` error before storing it | `d6ae4109` | `app/Services/CartService.php` |
| Coupon validator checks only the explicitly supplied code (locks the promotion row) | `d6ae4109` | `app/Actions/Admin/Discounts/ValidatePromotionPerCustomerLimitAction.php` |
| Cart-checkout coupon promotions are coupon-gated (`requires_coupon`) | `a1c1c28d` | `app/Actions/Admin/Discounts/CreateDiscountPromotionAction.php` |
| Admin `PUT /admin/orders/{order}` → completed settles counters | `15b51e49` | `app/Actions/Admin/Order/UpdateOrderAction.php` |
| Admin `POST /admin/orders` with `status = completed` settles counters | `15b51e49` | `app/Actions/Admin/Order/CreateOrderAction.php` |

Behaviour changes: an exhausted automatic promotion never appears in `applied_cart_discounts` and
never errors (A2/B3); one exhausted promotion no longer blocks or suppresses the others
(A10/A11); the checkout-level `coupon` 422 still exists for a code the customer explicitly
supplied, but the common case is caught earlier by `applyCoupon` under the `coupon_code` key
(A5/E4); `PerCustomerCouponLimitTest` was updated to assert the earlier rejection. Regression
tests cover all of it, including the two admin counter-settlement paths.

### 9.2 Round 2 — specs #149/#150/#148 on `refactor/discount-system`

| Finding | Closed by | Commit |
| --- | --- | --- |
| I6 — global caps not enforced against unpaid orders | Caps are decided from one grouped count of `DiscountPromotionUsage::active()` rows in both finders; completion counters became reporting-only. Concurrency stays **bounded overshoot** (spec #150 decision a), stated in `DiscountPromotionCreateData::bodyParameters()` | `b91cc3bd`, `36c0f88e`, `6f655a2f` |
| I8 — an abandoned pending order holds its slot | Reservation TTL: a hold older than `discounts.reservation_hold_minutes` (default 30) stops counting, evaluated at read time. `CancelAbandonedOrdersCommand` still skips orders that have any payment row, and no test asserts slot release from that command | `36c0f88e` |
| I13 — flat-amount coupons under-deliver | `ApplyFixedAmountOffAction` apportions exactly `min(amount, discountable worth)` over the discountable lines with largest-remainder integer shares | `7a8c87f4` |
| I17 — prepayment lines marked down inconsistently | One central discountable set (`OrderContextData::discountableItems()`) plus an `applyDiscount()` guard that no-ops on deposit and bundle lines; a registry-driven test walks every registered cart action | `f0d8089e`, `9d9004ca`, `9b302ea9` |
| I18 — reward actions write wallet rows during calculation | Pricing is side-effect free: reward actions append an intent, `orders.reward_intents_json` snapshots it, and `SettleCompletedOrderAction` grants it once at completion with a deterministic idempotency key | `6537bcc6`, `c6ec566d`, `55380b93`, merged `d9e20cb9` |
| I12 — deposit-only or bundle-only carts keep a code that consumes nothing | A coupon whose only effect is marking lines down is now refused at apply time with `shop.cart.errors.coupon_nothing_discountable` when no discountable line exists, so the code is never stored | `68d2b6b0` + the apply-time refusal in `CartService::applyCoupon()` |

### 9.3 Round 3 — review of this branch (2026-10-14)

| Finding | Kind | Closed by | Commit |
| --- | --- | --- | --- |
| I11 — a code that stops being applicable is dropped silently | Fixed | `CartData::$applied_coupon_error` (`shop.cart.errors.coupon_not_applied`) reports it on every cart read; the stale code stays on the cart and checkout is never blocked. The audit's original cap-accounting concern was checked and does **not** hold: both finders require an active coupon row for the typed code, so a deactivated code never reaches the cap comparison at all | `fb84423d` |
| I19 — the operator value the admin metadata publishes cannot be evaluated (new) | Fixed | `DiscountMetadataService::getOperators()` emits `greater_than_or_equal` while `CartValueConditionConfigData` could only cast `>=`, so a promotion configured from the published metadata threw `CannotCastEnum` on every cart it reached. `MathOperatorEnum::fromAlias()` + `MathOperatorCast` now accept both spellings, and validation accepts both | `bad8b1ec` |
| I20 — a gift line moved a threshold mid-calculation (new) | Fixed | `thresholdBasis(false)` read `subtotal_full_payment_items`, which `GiftProductAction` bumps by the gift's **list** price, so swapping two promotions' priority changed the discount. The full-payment side is now the build-time snapshot `effective_subtotal_discountable_items`; the legacy subtotals stay audit-only | `82ba23a1` |
| Admin promotion API examples documented a handler shape the API never returns (new) | Fixed | All five `resources/responses/admin/discount/promotion.*.json` examples regenerated through `DiscountPromotionData`: handler keys, real config keys, Jalali dates, the real `message`/`data`/`metadata` envelope, and the `cart_value_tier` / config-free `apply_tiered_percentage_off` pair | `54c15c1c` |
| Digest described the tier selection as an array payload | Fixed | `DIGEST_CORE_LOGIC.md` now names `TierData` | `dbecfaf6` |
| Admin metadata called the wallet-credit action a top-up of the withdrawable balance | Fixed | Name/description now read as a non-withdrawable bonus credit in both locales, and the published metadata examples follow | `111ff514` |
| Stacked promotions could erase the coupon code the order consumed (new, found reviewing this branch) | Fixed | `OrderCalculationService` accumulates `OrderContextData::$triggered_by_coupon_code`: per-promotion while a promotion's actions run, sticky after them. A later automatic promotion therefore neither inherits the code in its audit entry nor erases it, so the cart no longer reports a discount as "not applied" and `orders.applied_coupon_code` — which `IncrementDiscountUsageCountsAction` reads to increment the coupon's `usage_count` — keeps the code | `OrderCalculationService` + its test, this branch |
| I21 — a `cart_value_over` rule that omits `measure_whole_commitment` broke pricing (new) | Fixed | The DTO required the renamed key while validation only said `['boolean']`, so a stored rule from before the #149 rename (or any client omitting it) made `promotionConditionsPass()` throw `CannotCreateData` — a 500 on cart view and checkout — instead of measuring. The key now defaults to `false` (the old `include_prepayments = false` meaning) and the metadata reports it optional | `CartValueConditionConfigData` + tests + metadata fixtures, this branch |
| I22 — a reward-only promotion consumed no usage budget (new) | Fixed | Both the usage rows and the completion counters read `applied_cart_discounts_json`, which only gains an entry when the actions changed a price. A promotion whose only action was `add_wallet_credit`/`add_gift_credit` therefore recorded nothing: `usage_limit_total`, the coupon's `usage_limit` and the per-customer allowance were all decorative and the same code could be reused indefinitely (probe: 0 rows, counter 0, second order accepted another 5,000). `Order::usedPromotionIds()` now unions the discount log with `reward_intents_json`, and both actions read it | `RecordPromotionUsageAction`, `IncrementDiscountUsageCountsAction` + tests, this branch |

Also resolved on this branch: `refactor`/hygiene items split the single "count reservations vs cap"
comparison into `PromotionCapacity::exhausted()` (`0ea3b5d4`), moved the `reserved_usage_count`
load onto `DiscountPromotion` (`abea6918`), and consolidated the duplicated test fixtures into
`tests/Support/Helpers/DiscountHelpers.php` (`d7422a6c`).

Spec amendments made from this review (issue bodies, 2026-10-14):

- **#155 AC 4** now reads "measured on the same **price basis** as value conditions —
  product-effective, never base prices — over the **discountable line set**". The ladder keeps
  measuring `discountableTotal()`; the whole-commitment option stays a deliberate knob on
  `cart_value_over` only.
- **#157 AC 5** now names the admin promotion endpoints
  (`GET /api/v1/admin/discount-promotions` and `.../{id}`) as the reserved-capacity surface. The
  admin order preview deliberately does not carry campaign budget.

### 9.4 Still open

| Finding | Status |
| --- | --- |
| **I5 — `product_specific` promotions accept usage limits that are never enforced** | **Deferred by decision (2026-10-14).** The earlier ruling was to reject `usage_limit_total`/`usage_limit_per_customer` for `product_specific` in `DiscountPromotionCreateData` the way coupons already are; that was re-confirmed as *acceptable for now*, so the fields stay accepted and stay unenforced. Reverting to the ruling is a one-line validation change plus a test. |
| **I7 — refunds keep the slot; partial refunds count as consumed** | Decided, no action: a refund never restores quota (ADR-0003). |
| **I9 — a coupon-worded error for a promotion with no coupon** | Decided, no action: automatic promotions no longer raise a coupon error at all (round 1), and the remaining coupon-worded messages are accurate for the case that still raises them. |
| **I10 — admin order creation enforces the same per-customer limit** | Decided, no action (tested). |
| **I14 — the "no customer" guard is dead code** | Informational; kept as a defensive guard. |

### 9.5 Section 8 coverage gaps, updated

Closed since the audit was written: the admin completion-path counter increments (I15/I16) now have
tests. Still open: nothing asserts that `CancelAbandonedOrdersCommand` releases a usage slot (I8),
and the refund paths plus `partially_refunded` carry no usage-row assertion (I7). The remaining
gaps in section 8 stand as written.

Known trade-off introduced (unchanged): automatic-promotion exhaustion is evaluated when the
calculation runs, without the promotion-row lock, so two truly simultaneous checkouts can each
consume the last automatic slot (the schema only enforces unique `(promotion, order)`). The
explicit-coupon path keeps its `lockForUpdate` serialization.
