# Coupon usage tracked as an order-status state machine

We needed to enforce the per-customer coupon cap (`discount_promotions.usage_limit_per_customer`, a column that existed but was never read) and the total caps at checkout, for both shop and admin order creation. We decided that a user's coupon usage is derived from their orders' statuses — `pending`/`processing` reserve a slot, `completed`/`refunded` consume it, `cancelled`/`failed` release it — tracked in a link-to-order table (`discount_promotion_usages`: `promotion_id`, `coupon_id`, `customer_id`, `order_id`). We also unified the existing total counters (`usage_count`, `total_usage_count`) onto the same completion-based counting.

A bare counter column can't express release-on-cancel, and counting at order placement lets abandoned (pending, never-paid) orders permanently burn quota. Order status is the single source of truth, so usage follows it with no separate counter to keep in sync.
