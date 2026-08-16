# JeduShop — Wallet & Promotions

Wallet balance management, campaign-driven gift allocation, and referral bonuses for the Jedu e-commerce platform.

## Language

**Wallet Campaign**:
A configured promotion that credits users' gift balance. Has a type, amount, usage limits, a schedule, and an explicit threshold scope. Admin-created; triggered manually, by a domain event, or by a scheduled sweep.
_Avoid_: Offer

**Campaign Allocation**:
The act of crediting a user's gift balance from a wallet campaign. Recorded as an append-only ledger entry with a deterministic idempotency key and an `expires_at`.
_Avoid_: Grant, award

**Normal Balance**:
The customer's own topped-up funds. Spent *after* gift balance.
_Avoid_: Main balance, cash balance

**Gift Balance**:
The wallet sub-balance credited only by campaigns and promotions. Spent *before* normal balance, oldest gift first (FIFO), and expirable.
_Avoid_: Bonus balance (bonus is a transaction type, not a balance)

**Campaign Trigger**:
What fires a campaign: a domain event (`profile-completed`, `payment-completed`, `referral-completed`), a scheduled sweep (`birthday`, `seasonal`), or manual admin action.

**Profile Completion**:
When a customer has filled `first_name`, `last_name`, `email`, `civil_id`, `date_of_birth`, and `father_name`. The moment that triggers `registration_bonus` campaigns.
_Avoid_: Registration (registration is the OTP login; completion is the reward point)

**Threshold Scope**:
For `loyalty_reward` and `milestone_reward`: `lifetime` (cumulative across all history) or `windowed` (within `starts_at`..`ends_at`). Explicitly flagged so staff never create a lifetime campaign by accidentally leaving dates empty.

**Gift Expiry**:
The reclaim of unspent gift balance once a gift's `expires_at` passes, recorded as an `EXPIRY` transaction. Deadline is absolute (`ends_at`) or relative (receipt + N days).

**Referral**:
A customer invites another; when the invite completes, the referrer earns a `referral_bonus` campaign allocation. Blocked until a referral system exists.
_Avoid_: Invite, affiliate

> Distinction to keep sharp: a **Campaign** credits wallet gift balance; a **Promotion** (`DiscountPromotion`) discounts product prices. They are unrelated.
