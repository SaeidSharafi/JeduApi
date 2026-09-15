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

**Bundle**:
A fixed, non-customizable package of exact Product Delivery Options offered together through the three-layer catalog. The Bundle is the Productable definition, distinct from its purchasable Bundle Product Delivery Options.
_Avoid_: Learning Path, customizable package

**Bundle Product Delivery Option**:
The purchasable Bundle SKU whose `BUNDLE` delivery method delegates fulfillment to its component Product Delivery Options. It has no independent learning entitlement or provider delivery.
_Avoid_: Bundle Delivery Method, Bundle definition

**Bundle Purchase**:
The immutable commercial record of a customer's purchase of one Bundle Product Delivery Option. It groups the component fulfillment and accounting lines and is the only customer-facing unit for Bundle history, cancellation, and refund.
_Avoid_: Component Order Item, Enrollment

**Bundle Component Order Item**:
An internal fulfillment and accounting line created for one component of a Bundle Purchase. It supports the component's Enrollment, allocation, provisioning, and refund audit but is never independently purchased, presented, cancelled, or refunded.
_Avoid_: Bundle Purchase, standalone Order Item

**Learning Path**:
A long-lived, goal-oriented ordered guide composed of existing Productable identities. A Learning Path is not a Product, Product Delivery Option, Enrollment, or purchasable package. Its steps resolve to current Products for catalog information; Product archival or replacement does not change the path's stable Productable references.
_Avoid_: Route, journey, bundle, curriculum enrollment

**Purchase Eligibility**:
A customer is eligible to purchase an offering only when they have not previously acquired its educational content, directly or through a Bundle. Owning any Bundle component makes that Bundle ineligible.
_Avoid_: SKU-only ownership

**Component Allocation**:
The portion of a Bundle's selling price assigned to one component, representing the amount actually paid for that component.
_Avoid_: Component base price

**Vendor**:
The school department that owns and presents catalog offerings, including its departmental landing page. It is not an external marketplace seller or a financial-settlement boundary.
_Avoid_: Marketplace seller


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

### Providers & Integrations

**Provisioning Provider**:
An external system that a customer's or teacher's access is delivered through: IMS, Moodle, SpotPlayer, and the live-session panels BBB, Skyroom, and Niliroom. Niliroom is the panel behind BBB live sessions, and Moodle Quiz has no credentials of its own: it runs on the Moodle configuration.
_Avoid_: enrollment provider, integration, gateway

**SMS Gateway**:
The external provider that delivers transactional SMS (IPPanel today). One gateway serves every SMS Notification Option, and switching it off stops all sending.
_Avoid_: SMS provider, SMS service

**SMS Notification Option**:
One transactional SMS the platform can send — a login code, a refund confirmation, an order-paid notice, an enrollment-ready notice, a wallet-credit notice — each with its own enabled switch and pattern code.
_Avoid_: SMS template, SMS type

**Moodle Login Token**:
A Moodle web-service token whose only permission is to request a customer's login URL. Never interchangeable with the Moodle Service Token.
_Avoid_: SSO token, auth token

**Moodle Service Token**:
The Moodle web-service token used for administrative calls (users, courses, enrolments). It carries far wider access than the Moodle Login Token and must not be used to log customers in.
_Avoid_: admin token, API token

### Account Security

**Ban**:
Hard account suspension: revokes active tokens and blocks login. Stronger than a soft block/deactivation.
_Avoid_: suspend, block, deactivate, disable

**Device fingerprint**:
Server-side hash of IP address + User-Agent, used to correlate anonymous activity across requests. Not a persistent hardware ID.
_Avoid_: device ID, hardware fingerprint

### Administrative Data Operations

**Import Run**:
The tracked administrative operation created from an uploaded spreadsheet, including its preview, approval decision, local commit result, provider outcomes, and downloadable error report.
_Avoid_: import job, upload session

**Import Preview**:
The validation result for an uploaded spreadsheet before any local user or provider data is changed. It identifies valid rows, invalid rows, identity conflicts, and requested provider provisioning.
_Avoid_: dry run, validation-only import

**Provider Provisioning Request**:
An explicit row-level request to ensure that a user's account exists in a named external learning provider. It is additive: an absent or false request does not remove or disable an external account.
_Avoid_: provider enablement, provider sync

**Spreadsheet Template**:
The XLSX example file generated from an import contract, containing the supported headings, localized aliases, optional fields, provider request columns, and example values.
_Avoid_: sample upload, import example

### Inbound Requests

**Contact Request**:
A message submitted through the public contact form for staff follow-up.
_Avoid_: Contact Us entry, contact message

**Collaboration Request**:
A proposal submitted through the public collaboration form for staff review, optionally including a supporting attachment.
_Avoid_: Collaboration entry, application

**Inbound Request Status**:
The follow-up state shared by Contact Requests, Collaboration Requests, and Advice Requests: pending, contacted, resolved, or no response.
_Avoid_: State, stage

**Assigned Staff Member**:
The staff member currently responsible for following up an inbound request.
_Avoid_: Handler, last handled by

> Distinction to keep sharp: a **Campaign** credits wallet gift balance; a **Promotion** (`DiscountPromotion`) discounts product prices. They are unrelated.
