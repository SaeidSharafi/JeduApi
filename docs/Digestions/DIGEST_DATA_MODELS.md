# Digest: Data Models & Relationships

### User (`app/Models/User.php`)
- **Purpose:** Customer accounts for the e-commerce platform
- **Key Fields:** `uuid`, `first_name`, `last_name`, `email`, `phone`, `password`, `civil_id`, `civil_id_type`, `date_of_birth`, `gender`, `education_level`, `avatar_url`
- **Relationships:** 
  - `hasOne(Teacher::class)` - teacherData
  - `hasMany(Enrollment::class, 'customer_id')` - enrollments
  - `hasOne(Wallet::class)` - wallet
  - `hasMany(Review::class)` - reviews
  - `hasOne(Cart::class)` - cart
  - `hasMany(Order::class, 'customer_id')` - orders
  - `hasMany(UserDevice::class)` - devices
- **Helper Methods:** `hasSetPassword(): bool`, `profileCompleted(): bool` (requires `is_profile_completed` flag AND `civil_id` present), `routeNotificationForSms($notification): string` (returns phone)
- **Casts:** `civil_id_type` cast to `CivilIdTypeEnum`
- **Guard:** `user` (Sanctum authentication)

### Staff (`app/Models/Staff.php`)
- **Purpose:** Admin users with role-based permissions
- **Key Fields:** `name`, `email`, `password`, permission-related fields
- **Relationships:** 
  - Uses Spatie Permission package for roles/permissions
  - `hasMany(AdminActionLog::class, 'admin_id')` - actionLogs
- **Guard:** `staff` (Sanctum authentication)

### PersonalAccessToken (`app/Models/PersonalAccessToken.php`)
- **Purpose:** Sanctum token model override with lookup caching
- **Key Fields:** inherits Sanctum `personal_access_tokens` columns; token stored hashed
- **Special Features:** `findToken()` caches token lookups under `AccessToken::{sha256(plainToken)}` (360s, `_null_` sentinel for misses); `getTokenableAttribute()` caches the polymorphic User/Staff resolution per environment under `token_{id}::id_{env}` (360s); `save()` skips the database write when the only dirty attributes are `last_used_at`/`updated_at` (activity heartbeat does not hit storage).
- **Registration:** Bound in `AuthServiceProvider` so Sanctum resolves this class instead of the default token model.

### AdminActionLog (`app/Models/AdminActionLog.php`)
- **Purpose:** Audit trail for admin actions and compliance monitoring
- **Key Fields:** `admin_id`, `action_type`, `resource_type`, `resource_id`, `route_name`, `http_method`, `request_data`, `response_status`, `ip_address`, `user_agent`, `session_id`, `risk_level`, `metadata`
- **Relationships:**
  - `belongsTo(Staff::class, 'admin_id')` - admin
  - `morphTo()` - resource (polymorphic to any audited model)
- **Special Features:** Risk level categorization, wallet action detection, compliance reporting. `admin_id` nullable — system-triggered actions (automated provisioning) have no staff actor.

### Order (`app/Models/Order.php`)
- **Purpose:** Sales transaction records implementing WalletTransactionSourceableContract
- **Key Fields:** `increment_id`, `status`, `customer_id`, `total_item_count`, `subtotal`, `discount_amount`, `grand_total`, `full_value_grand_total`, `total_refunded`, `applied_coupon_code`
- **Relationships:**
  - `hasMany(OrderItem::class)` - items
  - `hasMany(Payment::class)` - payments
  - `hasMany(Enrollment::class, 'order_id')` - enrollments
  - `belongsTo(User::class, 'customer_id')` - customer
- **Computed Accessors:**
  - `totalProductDiscount()` — sums `product_discount_amount` from all order items via accessor; represents product-level discounts (featured prices, auto-promotions)
  - `totalCartDiscount()` — alias for `discount_amount`; represents cart-level discount (coupon)
  - `totalDiscount()` — sum of `total_product_discount` + `total_cart_discount`
  - `fullValueGrandTotal()` — internal accessor deriving the sum of item prices at original base values before any discounts; used as reference for `balance_due` calculation
- **Special Features:** Auto-incrementing order numbers generated via `OrderIncrementIdService` (transaction-safe), payment status calculations, two-layer discount tracking (product-level vs cart-level) with separate exposure via accessors. **Pre-payment model:** PENDING orders settle their remainder offline (in-person at the station), so a PENDING order's `balance_due` equals `full_value_grand_total`; partial online payments are not enabled — the `balance_due` accessor exists for future installment/rest-payment flows. `total_refunded` aggregates refunded amounts and feeds `balance_due` (`total_paid - total_refunded`).

### Product (`app/Models/Product.php`)
- **Purpose:** Sellable instances of educational content with polymorphic relationships
- **Key Fields:** `vendor_id`, `productable_id`, `productable_type`, `term_id`, `status`, `is_visible`, `short_name`, `name`, `slug`, `short_description`, `is_featured`, `price_data_cache`, `details_json`, `event_start_at`, `event_ended_at`, denormalized availability snapshot columns: `has_published_delivery_option`, `productable_status`, `is_term_active`, `earliest_registration_start`, `latest_registration_end`, `earliest_availability_start`, `latest_availability_end`, `near_capacity`, `max_capacity_utilization`
- **Relationships:**
  - `morphTo()` - productable (Course, Seminar, DigitalAsset)
  - `belongsTo(Vendor::class)` - vendor
  - `belongsTo(Term::class)` - term
  - `hasMany(ProductDeliveryOption::class)` - productDeliveryOptions
  - `hasManyThrough(OrderItem::class, ProductDeliveryOption::class)` - orderItems
  - `belongsToMany(self::class, 'related_products', 'product_id', 'related_product_id')` - relatedProducts (all types)
  - `relatedProductsOfType()` - related products filtered by RELATED type
  - `crossSellProducts()` - related products filtered by CROSS_SELL type
  - `upsellProducts()` - related products filtered by UPSELL type
- **Traits:** Uses `HasCategories`, `HasFactory`, `HasProductListingPresets`, and `Searchable` (aliased `search as scoutSearch`) for taxonomy tagging, database seeding, listing presets, and Scout/Typesense indexing
- **Attribute Scopes (Laravel `#[Scope]`):**
  - `publishedAndVisible()` — published + visible (+ productable/term checks; denormalized when `config('products.availability.use_denormalized')` is on)
  - `hasPublishedDeliveryOption()`, `publishedProductable()`, `activeTerm()` — component availability gates backed by denormalized columns or join-based fallbacks
  - `availabilityStatus(AvailabilityStatusEnum)` — PAST/UPCOMING/ONGOING temporal filter
  - `sortByCapacityUtilization(float $threshold = 0.8)` — orders by near-capacity flag then utilization ratio
  - `ofType(ProductableEnum)`, `inCategory(int)`, `search(?string)` — catalog query helpers
- **Special Features:** Publication-aware scopes combine status, visibility, and availability checks; SmartCache-backed price snapshots in `price_data_cache`; enum-backed casting for `status` with JSON casting on cached fields; supports product relationships (related, cross-sell, upsell) via pivot table with `relation_type` column. **Denormalized availability snapshot** (`has_published_delivery_option`, `productable_status`, `is_term_active`, `near_capacity`, `max_capacity_utilization`, window boundaries) is maintained by `UpdateProductAvailabilityJob` and used as an optional fast path via `config('products.availability.use_denormalized')` (default true) for `shouldBeSearchable()`, scopes, and search payloads. Search index payload captures availability windows (`earliest_registration_start_ts`, `latest_registration_end_ts`, `earliest_availability_start_ts`, `latest_availability_end_ts`), discount flags, scores, and event timestamps (`earliest_event_start_ts`, `latest_event_ended_ts`) for Typesense and PGroonga powered relevance ordering. **Event date scopes** (`eventEnded()`, `eventNotStarted()`, `eventOngoing()`, `eventNotEnded()`) filter products by temporal event state; `event_start_at`/`event_ended_at` datetime fields with indexes enable efficient querying for event-based products (seminars, workshops). `shouldBeSearchable()` returns true only when product is published, visible, productable published, has published delivery option, and term active (when denormalized mode enabled).

### Course (`app/Models/Course.php`)
- **Purpose:** Educational course definitions and blueprints
- **Key Fields:** `slug`, `thumbnail_url`, `full_name`, `short_name`, `description`, `duration`, `difficulty_level`, `career_prospects_text`, `curriculum_summary_text`, `outcomes_json`, `default_teacher_info`, `provides_certificate`, `faq`, `additional_info`, `properties`, review aggregates (`review_count`, `average_rating`), `meta_title`, `meta_description`, `meta_keywords`, `status`
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
  - `morphToMany(DigitalAsset::class, 'assetable')` - digitalAssets
  - `morphToMany(BlogPost::class, 'productable', 'blog_post_productables')` - blogPosts
  - `morphToMany(Category::class, 'categorizable', 'categorizables')` - categories
- **Traits:** Uses `HasMedia`, `HasReview`, `IsProductable`, and `Mediable` to centralize media handling, review aggregation (auto-maintained `review_count`/`average_rating`), and polymorphic product binding
- **Special Features:** Implements `ProductableContract` and `ReviewableContract`; participates in review aggregation events to keep cached review metrics synchronized; enum-backed casting for publication status and difficulty level; exposes course FAQs and certificate availability for storefront detail payloads

### Seminar (`app/Models/Seminar.php`)
- **Purpose:** One-off educational events
- **Key Fields:** `full_name`, `short_name`, `subtitle`, `slug`, `thumbnail_url`, `description`, `curriculum_summary_text`, `outcomes_json`, `target_audience`, `prerequisites`, `promo_video_external_url`, `estimated_duration_desc`, `difficulty_level`, `provides_certificate`, `faq`, `keywords`, review aggregates (`review_count`, `average_rating`), `meta_title`, `meta_description`, `meta_keywords`, `status`
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `hasMany(Review::class, 'reviewable_id')` - reviews (polymorphic)
  - `morphToMany(BlogPost::class, 'productable', 'blog_post_productables')` - blogPosts
- **Traits:** Combines `HasAssets`, `HasAuditor`, `HasCategories`, `HasMedia`, `HasReview`, `IsProductable`, and `Mediable` to manage attached resources, audit data, categories, and review aggregates
- **Special Features:** Curriculum structure uses `curriculum_summary_text` (text summary) and `outcomes_json` (structured learning outcomes array); difficulty level aligns with `CourseDifficultyLevelEnum` for catalog-wide filtering

### DigitalAsset (`app/Models/DigitalAsset.php`)
- **Purpose:** Standalone digital products (PDFs, videos, etc.)
- **Key Fields:** `short_name`, `full_name`, `slug`, `thumbnail_url`, `description`, `version`, `page_count`, `duration_seconds`, `is_attachable_to_course`, `difficulty_level`, `provides_certificate`, `faq`, review aggregates (`review_count`, `average_rating`), `keywords`, `meta_title`, `meta_description`, `meta_keywords`, `published_at`, `status`
- **Relationships:** 
  - Polymorphic relationship as `productable` to Product
  - `morphToMany(Category::class, 'categorizable', 'categorizables')` - categories
  - `morphedByMany(Course::class, 'assetable')` - courses
- **Traits:** Uses `HasMedia`, `HasReview`, `IsProductable`, and `Mediable` for media, review aggregation, and polymorphic bindings
- **Special Features:** Name split into `short_name` (max 100 chars) and `full_name` (max 191 chars) for display flexibility; difficulty level, FAQs, and certificate availability mirror course semantics for unified storefront filtering

### ProductDeliveryOption (`app/Models/ProductDeliveryOption.php`)
- **Purpose:** Specific purchase/delivery methods per product with pricing
- **Key Fields:** `uuid` (UUID v7 auto-generated), `sku` (optional, auto-generated if not provided), `name`, `price`, `capacity`, `enrolled_count`, `reserved_count`, `status`, `fulfillment_type`, `delivery_method`, `is_prepayment_available`, `prepayment_amount`, `is_featured`, `featured_price`, `featured_price_start_date`, `featured_price_end_date`, `registration_start_date`, `registration_end_date`, `available_from`, `available_to`, `access_days` (access duration from enrollment date), `details_json`
- **Relationships:**
  - `belongsTo(Product::class)` - product
  - `hasMany(ProductDeliveryOptionDiscountPrice::class)` - discountPrices
  - `belongsToMany(Teacher::class, 'product_delivery_option_teacher')` - teachers
  - `hasMany(Enrollment::class, 'product_delivery_option_id')` - enrollments
  - `hasMany(OrderItem::class)` - orderItems
- **Special Features:** UUID for external references, SKU auto-generation via `SkuGeneratorService` when not provided, capacity tracking backed by the persisted `enrolled_count` column (no more runtime `withCount`), and a `discountPrice` accessor that evaluates active discount windows using `starts_at`/`ends_at` timestamps on `ProductDeliveryOptionDiscountPrice`. **Capacity reservations:** `reserved_count` holds seats held by PENDING orders; committed seats = `enrolled_count + reserved_count`, and capacity validity checks compare `capacity > (enrolled_count + reserved_count)`. Reservations are managed by `ProductReservationService` (`reserve`/`consume`/`release`).

### Cart (`app/Models/Cart.php`)
- **Purpose:** Persistent shopping carts for both authenticated customers and guest sessions
- **Key Fields:** `user_id` (nullable), `guest_token` (UUID), `applied_coupon_code`
- **Relationships:**
  - `belongsTo(User::class)` - user
  - `hasMany(CartItem::class)` - items
- **Special Features:** Supports guest checkout via `guest_token`, enables post-login cart merges, and stores coupon context at the cart level

### CartItem (`app/Models/CartItem.php`)
- **Purpose:** Itemized cart entries representing specific `ProductDeliveryOption` selections
- **Key Fields:** `cart_id`, `product_delivery_option_id`, `payment_type`, `quantity`
- **Relationships:**
  - `belongsTo(Cart::class)` - cart
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
- **Special Features:** Casts `payment_type` to `OrderItemPaymentTypeEnum`, enforces uniqueness per cart/delivery option, and tracks multi-seat `quantity`

### ProductDeliveryOptionDiscountPrice (`app/Models/ProductDeliveryOptionDiscountPrice.php`)
 - **Key Fields:** `product_delivery_option_id`, `discount_promotion_id`, `discounted_price`, `starts_at`, `ends_at`, discount metadata
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
  - `belongsTo(DiscountPromotion::class)` - discountPromotion

### OrderItem (`app/Models/OrderItem.php`)
- **Purpose:** Individual line items within orders
- **Key Fields:** `order_id`, `product_delivery_option_id`, `qty_ordered`, `status`, `price`, `total`, `discount_amount`, `pricing_metadata`
- **Relationships:**
  - `belongsTo(Order::class)` - order
  - `belongsTo(ProductDeliveryOption::class)` - productDeliveryOption
  - `hasOne(Enrollment::class)` - enrollment
  - `hasMany(Refund::class)` - refunds
- **Computed Accessors:**
  - `originalPrice()` — base price from `pricing_metadata['original_price']`, falls back to `price` column
  - `productDiscountAmount()` — product-level discount from `pricing_metadata['discount_amount']` multiplied by `qty_ordered`; zero for pre-payment items
  - `totalDiscountAmount()` — sum of `product_discount_amount` + `discount_amount` (cart-level coupon)
- **Special Features:** Two-layer discount tracking: product-level discounts (featured prices, auto-promotions) stored in `pricing_metadata` JSON column; cart-level discounts (coupons) stored in `discount_amount` column. The `price` column always stores the base price from `product_delivery_option.price` with no discounts applied. The `pricing_metadata` JSON stores `{original_price, discount_type, discount_amount, discount_percentage}` — pre-payment items receive zero discount values in `pricing_metadata`.

### Enrollment (`app/Models/Enrollment.php`)
- **Purpose:** Student access records linking customers to purchased delivery options
- **Key Fields:** `uuid`, `order_id`, `order_item_id`, `customer_id`, `product_delivery_option_id`, `enrollment_status`, `access_start_date`, `access_end_date`, `external_enrollment_id`, `provisioning_data` (legacy JSONB execution data), `provisioning_plan` (non-null versioned JSONB provider applicability/readiness snapshot), `provisioning_status` (aggregate provisioning health)
- **Relationships:**
  - `belongsTo(User::class, 'customer_id')` - customer
  - `belongsTo(Order::class)` - order
  - `belongsTo(OrderItem::class)` - orderItem
  - `belongsTo(ProductDeliveryOption::class, 'product_delivery_option_id')` - productDeliveryOption
  - `hasOneThrough(Product::class, ProductDeliveryOption::class)` - product
- **Special Features:** UUID (`uuid7`) generation on create for external references; `ProvisioningPlanResolver` creates the canonical versioned provider matrix; enum-backed `enrollment_status` and `provisioning_status`; date casting for access window; JSON provisioning payloads and plan snapshots; no-provider paid enrollments can activate immediately. Provisioning failures remain occupying until recovery, so a paid Customer's capacity is not released. Dispatches `EnrollmentStatusChanged` on save/delete to keep projections synchronized. Dispatch is narrowed to occupancy-relevant changes only — the event fires when the enrollment is newly created or when `enrollment_status`/`product_delivery_option_id` change; access-date, notes, and provisioning metadata updates do not dispatch (they do not affect `enrolled_count`/availability).

### ProvisioningAttempt (`app/Models/ProvisioningAttempt.php`)
- **Purpose:** Durable lifecycle record for one queued provider execution.
- **Key Fields:** `uuid`, `enrollment_id`, `provider`, `trigger`, `status`, `sequence`, `retryable`, safe failure fields/metadata, `correlation_id`, optional `staff_id`, and lifecycle timestamps.
- **Relationships:** `belongsTo(Enrollment)` and optional `belongsTo(Staff)`.
- **Special Features:** Moodle attempts use the provider adapter boundary and generic queued job; attempt and enrollment updates are serialized with row locks, while provider HTTP calls execute outside those locks. Only canonical safe references are written to enrollment provisioning data.

### Payment (`app/Models/Payment.php`)
- **Purpose:** Financial transaction handling with multi-attempt transaction tracking
- **Key Fields:** `uuid`, `order_id` (nullable), `customer_id`, `amount`, `method`, `purpose` (PaymentPurposeEnum: ORDER, WALLET_TOPUP), `status`, `admin_notes`, `data`, `created_by`, `last_gateway_reference` (latest gateway ref), `attempt_count` (sequential attempt counter), `last_attempted_at`, `ip_address`, `user_agent`
- **Relationships:** 
  - `belongsTo(Order::class)` - order
  - `belongsTo(User::class, 'customer_id')` - customer
  - `hasMany(Refund::class)` - refunds
  - `hasMany(PaymentTransaction::class)` - transactions (per-attempt gateway tracking)
- **Special Features:** Uses `HasUuids` for globally unique references, enum-casts `method` to `PaymentMethodEnum` for processor routing, tracks payment attempts with `attempt_count`/`last_attempted_at`, stores last successful gateway reference for reconciliation, captures client IP/user agent for audit

### PaymentTransaction (`app/Models/PaymentTransaction.php`)
- **Purpose:** Per-attempt gateway transaction records for payment audit trail
- **Key Fields:** `payment_id`, `transaction_reference` (unique numeric ref starting at 200M), `attempt_number` (sequential per payment), `status` (PaymentTransactionStatusEnum: initiated/completed/failed), `gateway_request` (JSON — full request to gateway), `gateway_response` (JSON — full response from gateway), `initiated_at`, `completed_at`, `error_code`, `error_message`, `ip_address`, `user_agent`
- **Relationships:**
  - `belongsTo(Payment::class)` - payment
- **Special Features:** Sequential transaction references via `PaymentTransactionReferenceService` with row-locking for concurrency; full gateway request/response capture for debugging; lifecycle tracking with `initiated_at`/`completed_at` timestamps; error codes and messages for failure analysis

### Refund (`app/Models/Refund.php`)
- **Purpose:** Refund transaction records implementing `WalletTransactionSourceableContract` for wallet credit reversals
- **Key Fields:** `order_id`, `order_item_id`, `payment_id` (nullable — tracks which payment was refunded), `customer_id`, `created_by` (staff ID), `amount`, `deduction_amount`, `status` (`RefundStatusEnum: PENDING/COMPLETED/FAILED`), `transaction_details` (JSON — gateway-specific refund metadata), `admin_notes`, `refunded_at`
- **Relationships:** 
  - `belongsTo(Order::class)` - order
  - `belongsTo(OrderItem::class)` - orderItem
  - `belongsTo(Payment::class)` - payment
  - `belongsTo(User::class, 'customer_id')` - customer
- **Special Features:** SmartCache locking prevents concurrent refund processing; amount validation caps cumulative refunds against payment amount; gateway-specific refund processors via `RefundProcessorFactory` (Digipay, Manual, Wallet). Uses `HasAuditor` trait for staff attribution, `HasFactory` for test seeding.

### Review (`app/Models/Review.php`)
- **Purpose:** Customer review system for products and courses
- **Key Fields:** `user_id`, `reviewable_type`, `reviewable_id`, `rating`, `title`, `comment`, `status`, `is_featured`
- **Relationships:**
  - `belongsTo(User::class)` - user
  - `morphTo()` - reviewable (Product, Course, Seminar, DigitalAsset)
- **Special Features:** Rating system, featured reviews, approval workflow

### Category (`app/Models/Category.php`)
- **Purpose:** Hierarchical product organization with parent-child nesting
- **Key Fields:** `name`, `slug`, `parent_id`, `status`, `description`, `image_url`, `icon_url`, `educational_calendar_url`, `color_scheme`, `meta_title`, `meta_description`, `meta_keywords`, `properties`, `additional_info`, `is_good_for_start`
- **Relationships:** 
  - `belongsTo(self::class, 'parent_id')` - parent (self-referencing hierarchy)
  - `hasMany(self::class, 'parent_id')` - children (self-referencing hierarchy)
  - `morphToMany(Course::class, 'categorizable')` - courses
  - `morphToMany(DigitalAsset::class, 'categorizable')` - digitalAssets
  - `morphToMany(Seminar::class, 'categorizable')` - seminars
  - `morphToMany(Product::class, 'categorizable')` - products
  - `hasMany(Categorizable::class)` - categorizable pivot
- **Special Features:** "Good for Start" flagging, media management, parent-child relationship exposed in shop API via `CategoryCardData::$children`

### Categorizable (`app/Models/Categorizable.php`)
- **Purpose:** Pivot model for polymorphic category relationships
- **Key Fields:** `category_id`, `categorizable_type`, `categorizable_id`
- **Relationships:** Connects categories to various models

### RelatedProducts (Pivot Table: `related_products`)
- **Purpose:** Many-to-many self-referential relationships between products for merchandising (related, cross-sell, upsell)
- **Key Fields:** `id`, `product_id`, `related_product_id`, `relation_type`, `created_at`, `updated_at`
- **Relationship Types:** Uses `RelationTypeEnum` with values: `related` (similar/alternative products), `cross_sell` (frequently bought together), `upsell` (premium alternatives)
- **Relationships:** 
  - `product_id` FK to `products(id)` CASCADE
  - `related_product_id` FK to `products(id)` CASCADE
- **Special Features:** Unique constraint on (`product_id`, `related_product_id`, `relation_type`) prevents duplicates; indexed on `product_id` and `relation_type` for efficient queries; supports bulk attach/sync operations through `CreateRelatedProductAction`

### Teacher (`app/Models/Teacher.php`)
- **Purpose:** Instructor profiles with rich metadata and portfolio
- **Key Fields:** `uuid` (UUID v7 auto-generated), `first_name`, `last_name`, `bio`, `avatar_url`, `rate`, `email`, `phone`, `gender`, `social_links` (JSON)
- **Relationships:** 
  - `belongsToMany(ProductDeliveryOption::class)` - productDeliveryOptions (pivot: product_delivery_option_teacher)
  - `belongsTo(User::class)` - user
- **Special Features:** UUID auto-generation on create via boot method, avatar support for profile images, structured social media links in JSON format, rate field for pricing/expertise level, enum-backed gender casting

### Vendor (`app/Models/Vendor.php`)
- **Purpose:** Internal departments/external entities
- **Key Fields:** Vendor information and business details
- **Relationships:** `hasMany(Product::class)` - products

### Term (`app/Models/Term.php`)
- **Purpose:** Academic terms and scheduling periods
- **Key Fields:** Term dates, names, academic periods
- **Relationships:** `hasMany(Product::class)` - products

### DiscountPromotion (`app/Models/DiscountPromotion.php`)
- **Purpose:** Advanced discount/coupon system
- **Key Fields:** `name`, `description`, `type`, `is_active`, `starts_at`, `ends_at`, `priority` (higher runs first), `stop_processing_subsequent_rules` (blocks stacking), `requires_coupon`, `usage_limit_total`, `usage_limit_per_customer`, `total_usage_count`
- **Relationships:** 
  - `hasMany(DiscountPromotionRule::class)` - rules
  - `hasMany(DiscountCoupon::class)` - coupons
- **Special Features:** Complex rule-based discount system with conditions and actions; index on `(is_active, starts_at, ends_at)` and `(type, priority)`; usage counters (`total_usage_count` for non-coupon promotions) increment only on successful checkout; coupon gating via `requires_coupon` with `findPromotionByCoupon()` on `PromotionService`.

### DiscountPromotionRule (`app/Models/DiscountPromotionRule.php`)
- **Purpose:** Individual rules within discount promotions
- **Key Fields:** Rule conditions, operators, values, rule types
- **Relationships:** `belongsTo(DiscountPromotion::class)` - discountPromotion
- **Special Features:** Condition/action structure resolved through `DiscountHandlerRegistry` auto-discovery against the `DiscountConditionContract`/`DiscountActionContract` (cart) and `ProductDiscountConditionContract`/`ProductDiscountActionContract` (product) handler contracts; rule configuration schema (operators, parameter types) exposed via `DiscountMetadataService`.

### DiscountCoupon (`app/Models/DiscountCoupon.php`)
- **Purpose:** Coupon code management for discount promotions
- **Key Fields:** `code`, `usage_limit`, `used_count`, coupon metadata
- **Relationships:** `belongsTo(DiscountPromotion::class)` - discountPromotion

### Wallet (`app/Models/Wallet.php`)
- **Purpose:** User wallet system for credits and transactions
- **Key Fields:** `user_id` (unique — one wallet per user), `balance` (available in rials), `gift_balance` (non-withdrawable gift amounts), `status` (`WalletStatusEnum`: active/suspended/closed), `created_by` (nullable staff)
- **Relationships:**
  - `belongsTo(User::class)` - user
  - `hasMany(WalletTransaction::class)` - transactions
- **Special Features:** Balance/gift_balance snapshots captured on every transaction (`balance_after`, `gift_balance_after`); immutability enforced via `RecordWalletTransactionAction` using row locking (`lockForUpdate`) inside a DB transaction, idempotency-key dedup, and status enforcement (inactive wallets reject transactions with `WalletNotActive`).

### WalletTransaction (`app/Models/WalletTransaction.php`)
- **Purpose:** Individual wallet transaction records (immutable ledger entries)
- **Key Fields:** `wallet_id`, `user_id`, `type` (deposit/withdrawal/payment/refund/gift/bonus/adjustment/expiry), `amount` (positive=credit, negative=debit), `remaining_amount` (unspent slice of a gift/bonus credit, null for non-gift), `balance_after`, `gift_balance_after`, `source_type` (order/admin/promotion/refund/manual/system), `source_id`, `description`, `metadata` (JSONB), `expires_at` (promotional credits), `idempotency_key`, `created_by`
- **Relationships:** 
  - `belongsTo(Wallet::class)` - wallet
  - Polymorphic source tracking via `source_type`/`source_id`
- **Special Features:** Immutable audit trail; debit split between regular and gift balance tracked in metadata for ORDER/PAYMENT transactions; idempotency_key prevents duplicate recordings.

### ProductPrice (`app/Models/ProductPrice.php`)
- **Purpose:** Precomputed pricing index for fast product price lookups across the storefront
- **Key Fields:** `product_id`, `min_price`, `max_price`, `min_original_price`, `max_original_price`, `has_discount`, `has_featured_price`, `has_prepayment`, `discount_percentage`, `highest_discount_amount`
- **Relationships:**
  - `belongsTo(Product::class)` - product
- **Special Features:** Index table populated by `UpdateProductPricingJob` and maintained by `prices:index-all` command; provides efficient price range queries via `priceRange()`, discount filtering via `withDiscount()` and `withFeaturedPrice()` scopes; enables fast "products with discounts" queries without joins
- **Scopes:** `withDiscount()`, `withFeaturedPrice()`, `priceRange($min, $max)`
- **Methods:** `hasActiveDiscount()`, `isSinglePrice()`, `getDiscountAmount()`, `getPriceRange()`, `getEffectiveMinPrice()`

### WalletCampaign (`app/Models/WalletCampaign.php`)
- **Purpose:** Bulk wallet credit campaigns and promotions
- **Key Fields:** `threshold_scope` (ThresholdScopeEnum: lifetime|windowed), campaign details, allocation rules, eligibility criteria
- **Relationships:** Campaign management for bulk wallet operations
- **Special Features:** `threshold_scope` decides whether the threshold is measured across all history (lifetime, no dates) or within campaign dates (windowed, requires both starts_at/ends_at); validation rejects windowed without dates and lifetime with dates. Threshold values for event-driven reward campaigns live in `metadata`: `metadata.threshold_amount` (rials) for `loyalty_reward` (cumulative paid order total), `metadata.threshold_order_count` for `milestone_reward` (paid order count); evaluated by `EvaluateThresholdRewardAction` on payment completion.

### Setting (`app/Models/Setting.php`)
- **Purpose:** Application configuration registry powering CMS, storefront content, and integration credentials
- **Key Fields:** `key`, `value` (JSON payload — includes encrypted secrets for integration configs), `type`, `group`
- **Relationships:** Self-contained configuration system with media attachments via Mediable
- **Special Features:** `witImages()` helper resolves stored media IDs into `MediaData` DTOs; integrates with SettingsService and SmartCache invalidation; supports encrypted secret fields via `SettingKeyEnum::secretFields()`; secrets redacted from API responses via `SettingSecretRedactor`; SKIP_MEDIA optimization skips `witImages()` for integration keys (IMS, Moodle, BBB, SpotPlayer, Skyroom) to avoid unnecessary media queries
- **Setting Key Values:** Payment gateway configs under `SettingKeyEnum`: `MELLAT` (`payment.mellat`), `WALLET` (`payment.wallet`), `BANK_TRANSFER` (`payment.bank_transfer`), `DIGIPAY` (`payment.digipay`), `SKYROOM` (`skyroom`). Each gateway has its own `secretFields()` for encrypted credential storage at rest.

### HomePageBlock (`app/Models/HomePageBlock.php`)
- **Purpose:** Dynamic homepage block definitions rendered on the shop front
- **Key Fields:** `type` (enum), `title`, `location`, `content` (JSON), `order`, `is_active`
- **Relationships:** Media attachments for block imagery via Mediable
- **Special Features:** Enum-backed block type with boolean activation flag and ordering casts for precise layout control

### Slider (`app/Models/Slider.php`)
- **Purpose:** Homepage and promotional slider management with publication workflow
- **Key Fields:** `title`, `caption`, `image_id`, `image_url`, `image_alt`, `status`, `link`, `order`
- **Relationships:** Media attachments for slider images, `getImage()` helper returns hydrated `MediaData`
- **Special Features:** `active()` scope filters to published sliders via `PublicationStatusEnum`; leverages Mediable for tagged image retrieval

### Partner (`app/Models/Partner.php`)
- **Purpose:** Strategic partner logos and showcases for marketing sections
- **Key Fields:** `title`, `caption`, `image_id`, `image_url`, `image_alt`, `url`, `show_in`, `order`, `is_active`
- **Relationships:** Media attachments for partner imagery with `getImage()` helper returning `MediaData`
- **Special Features:** `active()` scope limits to visible partners; `show_in` enum targets specific storefront regions

### StudentStory (`app/Models/StudentStory.php`)
- **Purpose:** Success stories and testimonials showcased on the storefront
- **Key Fields:** `student_name`, `course_name`, `course_url` (validated as string, accepts relative paths like `/course-url`), `story_text`, `avatar_url`, `is_visible`, `is_featured`, `display_order`
- **Relationships:**
  - Media attachments (avatar) via Mediable; `avatar_url` persisted (not computed on the fly)
  - `belongsToMany(Category::class)` via `HasCategories`
  - `belongsToMany(Course::class, 'course_student_story')` - courses featured in the testimonial
- **Special Features:** `visible()` and `featured()` scopes drive storefront filtering; ordered display via `display_order`; category/course pivots enable admin/shop filtering; seeded demo data ships via `database/demo/student_stories.json`.

### course_student_story (Pivot)
- **Purpose:** Associates curated StudentStory testimonials with one or more Courses for filtering in admin/shop surfaces
- **Key Fields:** `course_id`, `student_story_id`
- **Constraints:** Composite primary key with cascading deletes to keep links in sync when either side is removed
- **Usage:** Powers admin filters (`filter[course_id]`) and shop queries (`course_slug`) when retrieving relevant testimonials.

### CollaborationRequest (`app/Models/CollaborationRequest.php`)
- **Purpose:** Incoming collaboration and partnership requests submitted from the shop
- **Key Fields:** `full_name`, `phone`, `email`, `department`, `message`
- **Relationships:** Media attachments for supporting documents via Mediable
- **Special Features:** Factory-backed model used by public collaboration form endpoints

### AdviceRequest (`app/Models/AdviceRequest.php`)
- **Purpose:** Callback requests for educational counseling follow-up
- **Key Fields:** `phone`, `status`, `note`, `handled_by_id`
- **Relationships:** `belongsTo(Staff::class, 'handled_by_id')` - handler
- **Special Features:** Enum-backed `status` with timestamps for handling workflow

---

### New Product-Related Enums

#### AvailabilityStatusEnum (`app/Enums/Product/AvailabilityStatusEnum.php`)
- **Values:** `PAST`, `UPCOMING`, `ONGOING`
- **Purpose:** Temporal state filter for product availability windows, used in `ProductQueryService::availabilityStatus()` for efficient filtering

#### ProductRegistrationStatusEnum (`app/Enums/Product/ProductRegistrationStatusEnum.php`)
- **Values:** `IN_PROGRESS`, `FINISHED`
- **Purpose:** Derived status in `ProductCardData` indicating whether registration is open for a product based on registration/availability dates aggregation

#### ProductDeliveryStatusEnum (`app/Enums/Product/ProductDeliveryStatusEnum.php`)
- **Values:** `ONLINE`, `IN_PERSON`, `COMBINED`
- **Purpose:** Derived delivery type in `ProductCardData` based on fulfillment types across delivery options

#### PaymentMethodEnum (`app/Enums/Payment/PaymentMethodEnum.php`)
- **`defaultConfig(): array`** — returns the default configuration array from `config/payments.php` for each gateway, used as fallback when no stored settings exist in the database.
- **`settingKey(): ?SettingKeyEnum`** — maps each gateway to its `SettingKeyEnum` for persisted configuration.

#### DeliveryMethodEnum (`app/Enums/Product/DeliveryMethodEnum.php`)
- **Values:** `LMS_MOODLE`, `VIDEO_PLATFORM_SPOTPLAYER`, `LIVE_SESSION_BBB`, `LIVE_SESSION_SKYROOM`, `DIRECT_DOWNLOAD`, `IN_PERSON`
- **Purpose:** Maps product delivery methods to external integration providers for provisioning routing

#### FulfillmentTypeEnum (`app/Enums/Product/FulfillmentTypeEnum.php`)
- **Values:** `ONLINE_SERVICE`, `OFFLINE_SERVICE`, `DIGITAL`, etc.
- **Purpose:** Groups delivery methods into fulfillment categories for filtering and provisioning

## Model Behavior Notes

### PaymentPurposeEnum (`app/Enums/Payment/PaymentPurposeEnum.php`)
- **Values:** `ORDER`, `WALLET_TOPUP`
- **Purpose:** Classifies payment records by business intent — order payments vs wallet top-ups. Used by `UpdateStatusesAfterPaymentListener` to route to appropriate handler (`OrderStatusService::handlePaymentCompletion()` for orders, `TopupWalletAction` for wallet top-ups).

### TransactionSourceEnum (`app/Enums/Wallet/TransactionSourceEnum.php`)
- **Additional Value:** `DEPOSIT`
- **Purpose:** Tracks wallet transactions originating from deposit/top-up payments.

### Order Provisioning Configuration
- `config/order.php` controls increment ID pattern (simple/dated/prefixed) and provisioning trigger (`any_payment`/`full_payment`/`manual_approval`).
- `config/payments.php` centralizes Mellat, bank transfer, and wallet gateway configurations plus transaction reference starting point (default: 200000001).

### Payment Transaction Tracking
- All payment processors create `PaymentTransaction` records for every gateway interaction.
- Transaction references are numeric-only sequential IDs beginning at 200000001 (configurable via `PAYMENT_TRANSACTION_START` env).
- Mellat gateway uses transaction reference (not order increment_id) as `orderId` in gateway requests.
- Wallet payments create immediate COMPLETED transaction records with wallet metadata.

### ProductDeliveryOption Capacity
- Capacity is enforced at checkout time; `enrolled_count` is used against `capacity` when creating orders.

### DiscountPromotion & DiscountCoupon Counters
- `DiscountPromotion.total_usage_count` and `DiscountCoupon.usage_count` increment only on successful checkout when the promotion/coupon is applied.

### Payment Verification State
- Payment verification starts with a gatekeeper check: if the payment is already `COMPLETED`, returns early; if the order has any other completed payment, throws `RuntimeException` preventing double-verification.
- Only `PENDING` payments transition to `COMPLETED`. Duplicate callback attempts against non-pending payments are blocked by the gatekeeper.
- Verification tracks full lifecycle via `PaymentTransaction` (INITIATED → COMPLETED/FAILED) with complete gateway request/response capture.

### Order & Enrollment Provisioning Triggers
- `config('order.provisioning.trigger')` controls auto-provisioning: `any_payment` (default), `full_payment`, or `manual_approval`.
- `manual_approval` requires staff to call `POST /api/v1/admin/order/{order}/approve` via `ApproveOrderAction`.

### Order & OrderItem Discount Snapshots
- `Order.applied_cart_discounts_json` captures cart-level discounts at checkout.
- `OrderItem.applied_discount_details_json` captures item-level discount details at checkout.

### ContactUsRequest (`app/Models/ContactUsRequest.php`)
- **Purpose:** Customer contact form submissions captured from the shop CMS
- **Key Fields:** `full_name`, `phone`, `subject`, `email`, `message`
- **Relationships:** Self-contained request records for support follow-up

### UserDevice (`app/Models/UserDevice.php`)
- **Purpose:** Device fingerprint records for registration velocity limiting
- **Key Fields:** `user_id`, `device_hash` (sha256 hex of ip+user_agent), `ip_address`, `user_agent`
- **Relationships:**
  - `belongsTo(User::class)` - user
- **Special Features:** One row per registration event; used by `RegistrationVelocityService` to enforce per-IP and per-device-hash daily caps

### SmsLog (`app/Models/SmsLog.php`)
- **Purpose:** SMS delivery tracking and logging
- **Key Fields:** `provider`, `status`, `to` (array of recipients), `message`, `data`, `sent_at`
- **Relationships:** Self-contained audit records for outbound SMS
- **Special Features:** Casts payload and recipient metadata to arrays for structured logging

### BlogCategory (`app/Models/Blog/BlogCategory.php`)
- **Purpose:** Hierarchical blog content organization
- **Key Fields:** `name`, `slug`, `description`, `parent_id`, `icon`, `meta_title`, `meta_description`, `meta_keywords`
- **Relationships:**
  - `belongsTo(self::class, 'parent_id')` - parent (self-referencing hierarchy)
  - `hasMany(self::class, 'parent_id')` - children (self-referencing hierarchy)
  - `belongsToMany(BlogPost::class, 'blog_post_category')` - posts
- **Special Features:** Media attachments for icons, SEO metadata fields for shop detail payloads, hierarchical structure similar to Category model, and published post counts sourced from the pivot.

### BlogPost (`app/Models/Blog/BlogPost.php`)
- **Purpose:** Blog content management with publication workflow and content relationships
- **Key Fields:** `title`, `slug`, `body`, `excerpt`, `author_id`, `status`, `published_at`, `read_time_minutes`, `is_featured`, `thumbnail_url`, `main_productable_id`, `main_productable_type`, `meta_title`, `meta_description`, `meta_keywords`
- **Relationships:**
  - `belongsTo(Staff::class, 'author_id')` - author
  - `belongsToMany(BlogCategory::class, 'blog_post_category')` - categories
  - `morphToMany(Course::class, 'productable', 'blog_post_productables')` - courses
  - `morphToMany(Seminar::class, 'productable', 'blog_post_productables')` - seminars
  - `morphToMany(DigitalAsset::class, 'productable', 'blog_post_productables')` - digitalAssets
  - `morphTo()` - mainProductable (single featured productable)
  - `morphMany(Review::class, 'reviewable')` - reviews
- **Traits:** Uses `HasMedia`, `HasReview`, and `Searchable` traits for standardized media management, review aggregation, and Scout/Typesense indexing
- **Special Features:** Publication workflow with DRAFT/PUBLISHED/SCHEDULED/ARCHIVED statuses, automated read time calculation, featured content system, polymorphic relationships to educational content, automatic cover image URL generation from media, and enriched storefront payloads supplying author data, active categories, SEO metadata, and media collections.
