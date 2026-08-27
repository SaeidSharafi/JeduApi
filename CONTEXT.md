# JeduShop

Wallet balance management, campaign-driven gift allocation, referral bonuses, and the commerce and account-security domains of the Jedu e-commerce platform.

## Language

### Wallet & Promotions

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

### Commerce

**Customer**:
The shop account (`User`) that places orders. Orders snapshot customer identity (`customer_*` fields). Per-customer limits are keyed on this.
_Avoid_: buyer, client, shopper, user

**Coupon**:
A redemption code (`discount_coupons.code`) that activates a Promotion. Has its own total usage cap.
_Avoid_: voucher, promo code, discount code

**Promotion**:
A discount rule set (`discount_promotions`) — conditions ("if") and actions ("then") evaluated against an order context. One Promotion can be activated by many Coupons. Carries total and per-customer usage caps.
_Avoid_: discount, offer, deal

**Digital Asset**:
A standalone digital product (e.g. PDF, video) sold as a Product. Each asset has one downloadable file (the main media). Sold via a `DIRECT_DOWNLOAD` delivery option.
_Avoid_: File, attachment, download

**Enrollment**:
A customer's purchased access to a specific product delivery option, bounded by an access window and enrollment status. Links customer, order item, and delivery option.
_Avoid_: Order item, registration, subscription

**Teacher**:
An instructor profile record linked to at most one customer account. Existence of the link grants the teacher dashboard. A Teacher may exist unlinked (public course-page profile) without granting any account access.
_Avoid_: instructor, professor

**Seminar**:
A live-session product delivered via a LIVE_SESSION delivery option (BBB/Niliroom or Skyroom). Teachers are attached through the delivery-option pivot, not through an IMS course code.
_Avoid_: online course, webinar

**Session Login URL**:
A single-use, short-lived URL that authenticates the current teacher into the live-session provider's panel/room without credentials. Generated per delivery method: Skyroom `createLoginUrl` (room entry as presenter) or Niliroom login grant (panel login + room redirect). Teachers get login URLs; students get join URLs.
_Avoid_: join URL, access link

**Niliroom room**:
The provider-side meeting room for a BBB seminar, created manually by staff in the Niliroom panel and referenced by its opaque public ID stored in the delivery option's details (`nili_room_id`). Never created via API.
_Avoid_: meeting, BBB room

**Skyroom room**:
The provider-side conference room for a Skyroom seminar, created manually in the Skyroom panel and referenced by its numeric ID stored in the delivery option's details (`room_id`). Never created via API.
_Avoid_: session, conference

### Account Security

**Ban**:
Hard account suspension: revokes active tokens and blocks login. Stronger than a soft block/deactivation.
_Avoid_: suspend, block, deactivate, disable

**Device fingerprint**:
Server-side hash of IP address + User-Agent, used to correlate anonymous activity across requests. Not a persistent hardware ID.
_Avoid_: device ID, hardware fingerprint

> Distinction to keep sharp: a **Campaign** credits wallet gift balance; a **Promotion** (`DiscountPromotion`) discounts product prices. They are unrelated.
