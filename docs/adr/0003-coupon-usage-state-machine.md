# Coupon usage tracked as an order-status state machine

We needed to enforce the per-customer coupon cap (`discount_promotions.usage_limit_per_customer`, a column that existed but was never read) and the total caps at checkout, for both shop and admin order creation. We decided that a user's coupon usage is derived from their orders' statuses — `pending`/`processing` reserve a slot, `completed`/`refunded` consume it, `cancelled`/`failed` release it — tracked in a link-to-order table (`discount_promotion_usages`: `promotion_id`, `coupon_id`, `customer_id`, `order_id`). We also unified the existing total counters (`usage_count`, `total_usage_count`) onto the same completion-based counting.

A bare counter column can't express release-on-cancel, and counting at order placement lets abandoned (pending, never-paid) orders permanently burn quota. Order status is the single source of truth, so usage follows it with no separate counter to keep in sync.

## Amendment: caps counted from reservations (#150, #157)

The decision above stands — order status is still the single source of truth for a usage row, rows are still created at order placement and deleted on cancellation/failure. What changed is which number decides a cap.

`discount_promotions.usage_limit_total` and `discount_coupons.usage_limit` are now compared against the count of *active reservations* (`DiscountPromotionUsage::active()`, per promotion and per coupon), not against the completion counters. A pending or processing order therefore holds its slot from placement, and a cap reads as full before any holding order completes. The completion counters (`total_usage_count`, `usage_count`) keep their meaning — incremented exactly once at completion, never decremented — and become the reporting metric; admin data exposes reserved capacity alongside them.

The abandoned-hold problem that argued against placement counting is handled by the reservation window rather than by counting at completion: a held row stops counting after `config('discounts.reservation_hold_minutes')` (default 30 minutes), while a completed or refunded row counts forever. Expiry is evaluation-only; the row is not deleted.

Concurrency is not serialized, so a cap may be overshot by the number of genuinely simultaneous checkouts — an accepted bounded overshoot (spec #150, decision a). Caps are budget targets, and that bound is stated where admins configure them. Revisit with an atomic reservation counter if a cap ever becomes a hard commitment.
