# Complete Application Database Schema

Reference for uploaded-doc chat: tables, columns, indexes, and constraints. Read affected tables. Model semantics in `DIGEST_DATA_MODELS.md` when uploaded. Repo access optional; missing details → flag gap. Reading guide: `CODEBASE_DIGEST.md` when uploaded.

## User & Staff Management

### Table: `users`
- Purpose: Stores customer account information.
- Columns:
  - id (BIGINT, PK)
  - uuid (UUID, unique, indexed) — public identifier
  - first_name (VARCHAR(100), nullable)
  - last_name (VARCHAR(100), nullable)
  - phone (VARCHAR(20), unique)
  - phone2 (VARCHAR(20), nullable)
  - phone_verified_at (TIMESTAMP, nullable)
  - email (VARCHAR(255), unique, nullable)
  - email_verified_at (TIMESTAMP, nullable)
  - password (VARCHAR(255), nullable)
  - civil_id (VARCHAR(255), nullable)
  - civil_id_type (VARCHAR(255), nullable)
  - date_of_birth (DATE, nullable)
  - father_name (VARCHAR(100), nullable)
  - gender (VARCHAR(255), nullable)
  - education_level (VARCHAR(20), nullable)
  - field_of_study (VARCHAR(255), nullable)
  - education_status (VARCHAR(20), nullable)
  - created_at/updated_at (TIMESTAMPS)
- Indexes:
  - PK(id)
  - UNIQUE(uuid), UNIQUE(phone), UNIQUE(email), UNIQUE(civil_id_type, civil_id)
  - INDEX(uuid)
  - INDEX(last_name, first_name), INDEX(civil_id), INDEX(date_of_birth)

### Table: `staff`
- Purpose: Administrative accounts.
- Columns:
  - id (BIGINT, PK)
  - name (VARCHAR, nullable)
  - email (VARCHAR, unique, nullable)
  - phone (VARCHAR(20), unique)
  - password (VARCHAR, nullable)
  - is_admin (BOOLEAN, default false)
  - remember_token (VARCHAR(100), nullable)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id), UNIQUE(email), UNIQUE(phone)

### Table: `teachers`
- Purpose: Instructor profiles.
- Columns:
  - id (BIGINT, PK)
  - first_name (VARCHAR)
  - last_name (VARCHAR)
  - bio (TEXT)
  - rate (FLOAT, nullable)
  - email (VARCHAR, unique)
  - phone (VARCHAR, nullable)
  - gender (VARCHAR, nullable)
  - birth_date (DATE, nullable)
  - social_links (JSON, nullable)
  - user_id (BIGINT) FK -> users(id) RESTRICT
  - created_by (BIGINT, nullable) FK -> staff(id) SET NULL
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id), UNIQUE(email)

### Table: `user_devices`
- Purpose: Device fingerprint records for registration velocity limiting.
- Columns:
  - id (BIGINT, PK)
  - user_id (BIGINT) FK -> users(id) CASCADE
  - device_hash (VARCHAR(64), indexed) — sha256(ip . user_agent) hex
  - user_agent (VARCHAR(500), nullable)
  - ip_address (VARCHAR(45), nullable, indexed)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id), INDEX(device_hash), INDEX(ip_address)

---
## Product & Catalog Management

### Table: `bundles`
- Purpose: Productable Bundle definitions used by the normal Product catalog workflow.
- Columns: `id`, `slug` (unique), `full_name`, `short_name`, `description`, `thumbnail_url`, `properties` (JSON), `additional_info` (JSON), `faq` (JSON), `status`, `created_by` (nullable FK to staff, SET NULL), timestamps.

### Table: `learning_paths`
- Purpose: Non-commercial Learning Path aggregate for staff-authored guided content.
- Columns: `id`, `title`, `slug` (unique), `description`, `introduction_title`, `introduction_description`, `conclusion_title`, `conclusion_description`, nullable `thumbnail_url` (denormalized URL of the first submitted cover media), optional `meta_title` (VARCHAR(70)), `meta_description` (VARCHAR(160)), `meta_keywords` (VARCHAR(255)), `display_order` (unsigned integer, default 0), `status` (VARCHAR, default `draft`, indexed), timestamps.
- Constraints: The slug is unique and editable. This table has no Product, ProductDeliveryOption, cart, order, or enrollment foreign key.

### Table: `learning_path_steps`
- Purpose: Ordered Learning Path editorial steps referencing existing productable records.
- Columns: `id`, `learning_path_id` (FK -> `learning_paths.id`, CASCADE), `position` (unsigned integer), `productable_type` (morph alias), `productable_id` (BIGINT), `title`, `description`, timestamps.
- Constraints and indexes: UNIQUE(`learning_path_id`, `position`) keeps positions unique; UNIQUE(`learning_path_id`, `productable_type`, `productable_id`) prevents duplicate references inside a path; INDEX(`productable_type`, `productable_id`) supports reference lookup and productable deletion guards. The polymorphic reference is intentionally not a copied Product and has no commercial FK.

### Table: `vendors`
- Purpose: Product vendors/departments.
- Columns:
  - id (BIGINT, PK)
  - name (VARCHAR)
  - email (VARCHAR, nullable)
  - phone (VARCHAR(20), nullable)
  - phone2 (VARCHAR(20), nullable)
  - address (TEXT, nullable)
  - map_location (TEXT, nullable)
  - logo_url (VARCHAR, nullable)
  - favicon_url (VARCHAR, nullable)
  - social_links (JSON, nullable)
  - theme_options (JSON, nullable)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id)

### Table: `organization_pages`
- Purpose: Dedicated singleton configuration for the one-off Organization page.
- Singleton enforcement: organization_pages.singleton_key is an unsigned tiny integer defaulting to 1 with a UNIQUE constraint; the migration seeds singleton_key = 1 and application reads target that fixed key.
- Columns: `id` (BIGINT, PK), `singleton_key` (UNSIGNED TINYINT, default 1, UNIQUE), nullable `vendor_id` (FK -> `vendors.id`, RESTRICT), nullable `hero_title` (VARCHAR), nullable `hero_description` (TEXT), nullable `ims_portal_url` (VARCHAR(2048)), nullable `request_section_title` (VARCHAR), nullable `request_section_explanation` (TEXT), nullable `faqs` (JSON), nullable `hero_image_url` (VARCHAR(2048)), nullable `educational_calendar_url` (VARCHAR(2048)), timestamps.
- Initialization and constraints: The migration inserts the initial row with no Vendor and all optional content unset. The page uses `mediables` attachments tagged `hero` and `educational_calendar` for admin management; their public URLs are denormalized into the two URL columns for shop reads, so the shop endpoint does not load media. There is no `hero_image_id` column. Vendor deletion is rejected by the Vendor action while this row references it. FAQ array order is display order and each object contains `question`, `answer`, and `is_visible`. Partner data is not stored here.

### Table: `organization_training_requests`
- Purpose: Public Organization training enquiries, stored separately from collaboration/contact requests and existing Course records.
- Columns: `id` (BIGINT, PK), `uuid` (UUID, unique public reference), `first_name` (VARCHAR(100)), `last_name` (VARCHAR(100)), `phone` (VARCHAR(20)), `position` (VARCHAR), `organization_name` (VARCHAR), `requested_course_names` (JSON), nullable `notes` (TEXT), `status` (VARCHAR, indexed, default `pending`), nullable `assigned_to_id` (FK -> `staff.id`, SET NULL), nullable `vendor_id` (FK -> `vendors.id`, RESTRICT), nullable `vendor_snapshot` (JSON), timestamps.
- Indexes and constraints: UNIQUE(uuid), INDEX(status), INDEX(assigned_to_id), INDEX(vendor_id); one request has at most one Mediable `attachment` tag. The status uses the shared `InboundRequestStatusEnum` values (`pending`, `contacted`, `resolved`, `no_response`).
- Submission invariants: The public DTO requires first name, last name, phone, position, and organization name, plus at least one non-empty manually entered course name or one PDF attachment. PDFs are content-validated and capped at 2 MB. The write transaction snapshots the current Organization page Vendor as `{id, name}`, stores the attachment on the private `local` disk, and emits no notification on validation or storage failure. New rows are pending and unassigned.
- Privacy and history: Public responses expose only the UUID reference. Staff responses expose attachment metadata and a protected download route. `vendor_id` preserves the deletion guard while `vendor_snapshot` preserves the submission-time Vendor name after page reassignment.

### Table: `terms`
- Purpose: Academic terms/scheduling periods.
- Columns:
  - id (BIGINT, PK)
  - name (VARCHAR)
  - status (VARCHAR, default inactive, indexed)
  - academic_year (VARCHAR, nullable)
  - start_date (DATE, nullable)
  - end_date (DATE, nullable)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id), INDEX(status)

### Table: `categories`
- Purpose: Product categories (hierarchical).
- Columns:
  - id (BIGINT, PK)
  - parent_id (BIGINT, nullable) FK -> categories(id) RESTRICT
  - name (VARCHAR, unique)
  - slug (VARCHAR, unique)
  - status (VARCHAR, default published, indexed)
  - description (VARCHAR, nullable)
  - image_url (VARCHAR, nullable)
  - icon_url (VARCHAR, nullable)
  - educational_calendar_url (VARCHAR, nullable)
  - color_scheme (VARCHAR, nullable)
  - meta_title (VARCHAR(70), nullable)
  - meta_description (VARCHAR(160), nullable)
  - meta_keywords (VARCHAR(255), nullable)
  - properties (JSON, nullable)
  - additional_info (JSON, nullable)
  - created_by (BIGINT, nullable) FK -> staff(id) SET NULL
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id), UNIQUE(name), UNIQUE(slug), INDEX(status)

### Tables: `courses`, `seminars`, `digital_assets`
  - id (BIGINT, PK)
  - slug (VARCHAR unique) [courses, seminars, digital_assets]
  - status (VARCHAR, default draft, indexed)
  - name/full_name, description (TEXT/VARCHAR as per table)
  - meta_title (VARCHAR(70) nullable), meta_description (VARCHAR(160) nullable), meta_keywords (VARCHAR(255) nullable)
  - created_by (BIGINT nullable) FK -> staff(id) SET NULL
  - review_count (INT default 0), average_rating (DECIMAL(3,2) default 0.0)
  - created_at/updated_at (TIMESTAMPS)
  - short_name (VARCHAR nullable), thumbnail_url (VARCHAR nullable), sample_certificate_image_url (VARCHAR nullable)
  - duration (INT nullable), difficulty_level (VARCHAR), career_prospects_text (TEXT nullable)
  - curriculum_summary_text (TEXT nullable), outcomes_json (JSON nullable)
  - default_teacher_info (TEXT nullable), provides_certificate (BOOLEAN default false), faq (JSON nullable)
  - additional_info (JSON nullable), properties (JSON nullable)
 - Indexes:
   - INDEX(status)
   - FULLTEXT (slug, name, description) where supported
  - short_name (VARCHAR not null per migration), subtitle (VARCHAR nullable), slug (unique)
  - description (TEXT not null), thumbnail_url (VARCHAR nullable)
  - curriculum_summary_text (TEXT nullable), outcomes_json (JSON nullable) [replaces learning_objectives]
  - target_audience (TEXT nullable), prerequisites (TEXT nullable)
  - promo_video_external_url (VARCHAR nullable), estimated_duration_desc (VARCHAR nullable), difficulty_level (VARCHAR nullable)
  - provides_certificate (BOOLEAN default false)
  - faq (JSON nullable), keywords (TEXT nullable)
  - Indexes: FULLTEXT (full_name, short_name, slug, description, keywords) plus optional PGroonga index when using PostgreSQL
  - short_name (VARCHAR(100) not null), full_name (VARCHAR(191) not null) [replaces name]
  - slug (unique), description (TEXT nullable)
  - thumbnail_url (VARCHAR nullable), version (VARCHAR(50) nullable)
  - page_count (INT unsigned nullable), duration_seconds (INT unsigned nullable)
  - is_attachable_to_course (BOOLEAN default false), status (VARCHAR indexed)
  - keywords (TEXT nullable), published_at (TIMESTAMP nullable)

### Table: `products`
- Purpose: Sellable entity linked to productable.
- Columns:
  - id (BIGINT, PK)
  - vendor_id (BIGINT) FK -> vendors(id)
  - productable_id (BIGINT)
  - productable_type (VARCHAR)
  - term_id (BIGINT) FK -> terms(id)
  - status (VARCHAR default draft, indexed)
  - is_visible (BOOLEAN default false, indexed)
  - short_description (VARCHAR)
  - short_name (VARCHAR)
  - name (VARCHAR)
  - slug (VARCHAR) — not unique here
  - is_featured (BOOLEAN default false, indexed)
  - price_data_cache (JSONB nullable)
  - details_json (JSONB not null)
  - created_at/updated_at (TIMESTAMPS)
- Indexes:
  - INDEX(status), INDEX(is_visible), INDEX(is_featured)
  - INDEX(productable_type, productable_id)
  - INDEX(vendor_id, term_id)
  - INDEX(status, is_visible)

### Table: `product_delivery_options`
- Purpose: Purchase options with pricing/schedule.
- Columns:
  - id (BIGINT, PK)
  - uuid (UUID not null, unique) — external referencing identifier
  - product_id (BIGINT) FK -> products(id) CASCADE
  - name (VARCHAR)
  - sku (VARCHAR nullable, unique) [auto-generated via SkuGeneratorService if not provided]
  - fulfillment_type (VARCHAR)
  - delivery_method (VARCHAR)
  - price (BIGINT)
  - capacity (INT nullable)
  - allow_multiple_quantity (BOOLEAN default false)
  - status (VARCHAR default draft, indexed)
  - is_prepayment_available (BOOLEAN default false)
  - prepayment_amount (BIGINT nullable)
  - details_json (JSONB not null)
  - is_featured (BOOLEAN default false)
  - featured_price (BIGINT nullable)
  - featured_price_start_date (DATETIME nullable)
  - featured_price_end_date (DATETIME nullable)
  - registration_start_date (DATE nullable)
  - registration_end_date (DATE nullable)
  - available_from (DATE nullable)
  - available_to (DATE nullable)
  - access_days (INT unsigned nullable, default null) — Number of days user has access to content from enrollment date; null means unlimited
  - composition_version (INT unsigned default 1) — Monotonic Bundle composition revision
  - bundle_review_required_at (TIMESTAMP nullable)
  - bundle_review_reasons (JSON nullable) — Stable material-change reason values
  - created_at/updated_at (TIMESTAMPS)
- Indexes: UNIQUE(sku), INDEX(status), INDEX(delivery_method, fulfillment_type)

### Pivot: `bundle_components`
- Columns:
  - id (BIGINT, PK)
  - bundle_product_delivery_option_id (BIGINT) FK -> product_delivery_options(id) CASCADE
  - component_product_delivery_option_id (BIGINT) FK -> product_delivery_options(id) RESTRICT
  - allocation (BIGINT unsigned)
  - created_at/updated_at (TIMESTAMPS)
- Constraints: UNIQUE(bundle_product_delivery_option_id, component_product_delivery_option_id), indexes on both foreign keys

### Pivot: `product_delivery_option_teacher`
- Columns:
  - product_delivery_option_id (BIGINT) FK -> product_delivery_options(id) CASCADE
  - teacher_id (BIGINT) FK -> teachers(id) RESTRICT
- Keys: PRIMARY(product_delivery_option_id, teacher_id)

### Table: `cart_items`
- Columns include `composition_version` (INT unsigned nullable), capturing the Bundle PDO composition revision added to the cart. Checkout rejects a stale revision.

### Table: `related_products`
- Purpose: Product merchandising relationships (related, cross-sell, upsell)
- Columns:
  - id (BIGINT, PK)
  - product_id (BIGINT not null) FK -> products(id) CASCADE
  - related_product_id (BIGINT not null) FK -> products(id) CASCADE
  - relation_type (VARCHAR default 'related') [Enum: related, cross_sell, upsell]
  - created_at/updated_at (TIMESTAMPS)
- Indexes:
  - INDEX(product_id, relation_type) [for filtering by product and type]
  - INDEX(related_product_id) [for reverse lookups]
  - UNIQUE(product_id, related_product_id, relation_type) named 'unique_product_relation' [prevents duplicate relationships]
- Special Features: Supports bulk attach/sync operations; validates product cannot be related to itself

### Table: `product_prices`
- Purpose: Pricing index for fast querying with discount/featured price calculations.
- Columns:
  - id (BIGINT, PK)
  - product_delivery_option_id (BIGINT, unique) FK -> product_delivery_options(id) CASCADE
  - base_price (BIGINT not null) [regular price]
  - final_price (BIGINT not null) [after applying best discount]
  - discount_id (BIGINT nullable) FK -> discounts(id) SET NULL [best applicable discount]
  - discount_amount (BIGINT default 0) [discount value applied]
  - has_discount (BOOLEAN default false, indexed)
  - featured_price (BIGINT nullable) [promotional price]
  - featured_price_active (BOOLEAN default false, indexed) [true if within featured date range]
  - featured_price_start_date (DATETIME nullable)
  - featured_price_end_date (DATETIME nullable)
  - is_available (BOOLEAN default true, indexed) [based on registration/availability dates]
  - created_at/updated_at (TIMESTAMPS)
- Purpose: Denormalized pricing data for efficient querying and filtering
- Updated by: UpdateProductPricingJob (queued), CheckExpiredFeaturedPricesCommand (scheduled)
- Indexes: UNIQUE(product_delivery_option_id), INDEX(has_discount), INDEX(featured_price_active), INDEX(is_available)

---
## Order & Payment Management

### Table: `orders`
- Purpose: Order header/summary.
- Columns:
  - id (BIGINT, PK)
  - increment_id (VARCHAR unique)
  - status (VARCHAR default pending, indexed)
  - customer_id (BIGINT) FK -> users(id)
  - customer_email (VARCHAR)
  - customer_phone (VARCHAR)
  - customer_first_name (VARCHAR)
  - customer_last_name (VARCHAR)
  - customer_snapshot_json (JSONB)
  - total_item_count (INT unsigned)
  - total_qty_ordered (INT unsigned)
  - subtotal (BIGINT)
  - discount_amount (BIGINT default 0)
  - tax_amount (BIGINT default 0)
  - grand_total (BIGINT)
  - full_value_grand_total (BIGINT default 0)
  - currency_code (VARCHAR default 'IRR')
  - applied_coupon_code (VARCHAR nullable)
  - admin_notes (TEXT nullable)
  - created_by (BIGINT nullable) FK -> staff(id) SET NULL
  - applied_cart_discounts_json (JSON nullable)
  - created_at/updated_at (TIMESTAMPS)
- Indexes:
  - UNIQUE(increment_id)
  - INDEX(status)
  - INDEX(customer_id, status) as idx_orders_customer_status
  - INDEX(customer_id, created_at) as idx_customer_created

### Table: `order_items`
- Purpose: Order line items. A nullable `bundle_purchase_id` makes a line an internal Bundle component rather than a standalone commercial item; the `(bundle_purchase_id, product_delivery_option_id)` uniqueness constraint prevents duplicate component lines.
- Columns:
  - id (BIGINT, PK)
  - order_id (BIGINT) FK -> orders(id) CASCADE
  - product_delivery_option_id (BIGINT nullable) FK -> product_delivery_options(id) SET NULL
  - vendor_id (BIGINT nullable) FK -> vendors(id) SET NULL
  - name (VARCHAR)
  - sku (VARCHAR)
  - product_data_snapshot_json (JSONB)
  - applied_discount_details_json (JSON nullable)
  - pricing_metadata (JSON nullable) — stores the immutable checkout snapshot: `{original_price, base_price_amount, paid_amount, product_discount_amount, cart_discount_amount, total_discount_amount, discount_type, discount_amount, discount_percentage}`. Pre-payment items receive zero discount metadata. Populated at order creation.
  - qty_ordered (INT default 1)
  - price (BIGINT) — base price from `product_delivery_option.price`, never includes discounts
  - total (BIGINT)
  - payment_type (VARCHAR)
  - prepayment_amount (BIGINT nullable)
  - discount_amount (BIGINT default 0) — cart-level coupon discount
  - tax_amount (BIGINT default 0)
  - total_refunded (BIGINT default 0)
  - qty_refunded (INT default 0)
  - status (VARCHAR default completed, indexed)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: INDEX(status), INDEX(status, created_at) as idx_status_created

### Table: `payments`
- Purpose: Order payments and wallet top-ups.
- Columns:
  - id (BIGINT, PK)
  - uuid (UUID unique)
  - order_id (BIGINT nullable) FK -> orders(id) CASCADE
  - purpose (VARCHAR 50, default 'order') — classifies payment: `order` or `wallet_topup`
  - customer_id (BIGINT) FK -> users(id) RESTRICT
  - amount (BIGINT)
  - method (VARCHAR)
  - status (VARCHAR default pending, indexed)
  - data (JSONB nullable)
  - admin_notes (TEXT nullable)
  - created_by (BIGINT nullable) FK -> staff(id) SET NULL
  - last_gateway_reference (VARCHAR nullable)
  - attempt_count (INTEGER default 0)
  - last_attempted_at (TIMESTAMP nullable)
  - ip_address (VARCHAR nullable)
  - user_agent (TEXT nullable)
  - created_at/updated_at (TIMESTAMPS)

### Table: `refunds`
- Purpose: Refunds per order item.
- Columns:
  - id (BIGINT, PK)
  - order_id (BIGINT) FK -> orders(id) CASCADE
  - order_item_id (BIGINT) FK -> order_items(id) CASCADE
  - customer_id (BIGINT nullable) FK -> users(id) SET NULL
  - created_by (BIGINT nullable) FK -> staff(id) SET NULL
  - amount (BIGINT)
  - deduction_amount (BIGINT)
  - status (VARCHAR default pending, indexed)
  - transaction_details (JSON)
  - refunded_at (TIMESTAMP nullable)
  - admin_notes (TEXT nullable)
  - created_at/updated_at (TIMESTAMPS)

### Table: `enrollments`
- Purpose: Access records resulting from orders.
- Columns:
  - id (BIGINT, PK)
  - uuid (UUID unique, indexed)
  - order_id (BIGINT) FK -> orders(id) CASCADE
  - order_item_id (BIGINT) FK -> order_items(id) CASCADE
  - customer_id (BIGINT) FK -> users(id) CASCADE
  - product_delivery_option_id (BIGINT) FK -> product_delivery_options(id) CASCADE
  - enrollment_status (VARCHAR default active)
  - access_start_date (DATE nullable)
  - access_end_date (DATE nullable)
  - provisioning_data (JSONB nullable) — per-provider execution payload with canonical references
  - provisioning_plan (JSONB not null, default version 1 empty provider plan) — canonical versioned applicability/readiness snapshot
  - provisioning_status (VARCHAR, default `healthy`, indexed) — aggregate provisioning health (`ready`, `in_progress`, `healthy`, `degraded`, `manual_action_required`)
  - revocation_status (VARCHAR nullable, indexed) — external access revocation lifecycle (`pending`, `failed`, `manual_action_required`, `revoked`); null when no revocation was ever required
  - notes (TEXT nullable)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: UNIQUE(uuid), INDEX(uuid)

---
### Table: `provisioning_attempts`
- Purpose: Provider execution attempts and their durable lifecycle state.
- Columns: `uuid`, `enrollment_id`, `provider`, `trigger`, `status`, `sequence`, `retryable`, safe failure code/message/metadata, `correlation_id`, optional `staff_id`, lifecycle timestamps, and `created_at`/`updated_at`.
- Indexes: UNIQUE(uuid), `(enrollment_id, provider, sequence)`, `(enrollment_id, provider, status)`, and INDEX(correlation_id).

---
## Discount & Wallet System

### Table: `discount_promotions`
- Purpose: Promotion master.
- Columns:
  - id (BIGINT, PK)
  - name (VARCHAR)
  - description (TEXT nullable)
  - type (VARCHAR)
  - is_active (BOOLEAN default false, indexed)
  - starts_at (TIMESTAMP nullable)
  - ends_at (TIMESTAMP nullable)
  - priority (INT default 0, indexed)
  - stop_processing_subsequent_rules (BOOLEAN default false)
  - usage_limit_total (INT nullable)
  - usage_limit_per_customer (INT nullable)
  - total_usage_count (INT default 0)
  - created_at/updated_at (TIMESTAMPS)
- Indexes:
  - INDEX(is_active, starts_at, ends_at) as idx_active_dates
  - INDEX(type, priority) as idx_type_priority

### Table: `discount_promotion_rules`
- Purpose: Promotion rules (conditions/actions).
- Columns:
  - id (BIGINT, PK)
  - discount_promotion_id (BIGINT) FK -> discount_promotions(id) CASCADE
  - type (VARCHAR)
  - handler (VARCHAR)
  - configuration (JSONB)
  - created_at/updated_at (TIMESTAMPS)

### Table: `discount_coupons`
- Purpose: Coupon codes.
- Columns:
  - id (BIGINT, PK)
  - discount_promotion_id (BIGINT) FK -> discount_promotions(id) CASCADE
  - code (VARCHAR unique)
  - usage_limit (INT nullable)
  - usage_count (INT default 0)
  - is_active (BOOLEAN default true)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: UNIQUE(code), INDEX(is_active, code) as idx_active_code

### Table: `product_delivery_option_discount_prices`
- Purpose: Precomputed discounted prices per option and promotion.
- Columns:
  - product_delivery_option_id (BIGINT, PK) FK -> product_delivery_options(id) CASCADE
  - discount_promotion_id (BIGINT) FK -> discount_promotions(id) CASCADE
  - discounted_price (BIGINT)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(product_delivery_option_id), INDEX(product_delivery_option_id, discount_promotion_id) as idx_pdo_promotion

### Table: `wallets`
- Purpose: User wallet balances.
- Columns:
  - id (BIGINT, PK)
  - user_id (BIGINT unique) FK -> users(id) CASCADE
  - balance (BIGINT default 0)
  - gift_balance (BIGINT default 0)
  - status (VARCHAR default active, indexed)
  - created_by (BIGINT nullable) FK -> staff(id) SET NULL
  - created_at/updated_at (TIMESTAMPS)
- Indexes: UNIQUE(user_id), INDEX(status)

### Table: `wallet_transactions`
- Purpose: Ledger of wallet changes.
- Columns:
  - id (BIGINT, PK)
  - wallet_id (BIGINT) FK -> wallets(id) CASCADE
  - user_id (BIGINT) FK -> users(id) RESTRICT
  - type (VARCHAR) — deposit/withdrawal/payment/refund/gift/bonus/adjustment/expiry
  - amount (BIGINT)
  - remaining_amount (BIGINT nullable) — unspent slice of a gift/bonus credit; null for non-gift transactions
  - balance_after (BIGINT)
  - gift_balance_after (BIGINT)
  - source_type (STRING)
  - source_id (BIGINT nullable)
  - description (TEXT nullable)
  - metadata (JSONB nullable)
  - expires_at (TIMESTAMP nullable)
  - created_by (BIGINT nullable) FK -> staff(id) SET NULL
  - created_at/updated_at (TIMESTAMPS)
- Indexes: INDEX(wallet_id), INDEX(user_id), INDEX(type), INDEX(source_type, source_id), INDEX(created_at), INDEX(expires_at)

### Table: `wallet_campaigns`
- Purpose: Gift/bonus campaigns.
- Columns:
  - id (BIGINT, PK)
  - name (VARCHAR)
  - description (TEXT nullable)
  - type (VARCHAR(50))
  - threshold_scope (VARCHAR default 'lifetime') INDEX: lifetime|windowed — windowed requires both dates, lifetime requires none
  - is_active (BOOLEAN default true)
  - amount (BIGINT)
  - usage_limit_total (INT nullable)
  - usage_limit_per_user (INT nullable default 1)
  - total_usage_count (INT default 0)
  - starts_at (TIMESTAMP nullable)
  - ends_at (TIMESTAMP nullable)
  - metadata (JSONB nullable)
  - created_by (BIGINT nullable) FK -> staff(id) SET NULL
  - created_at/updated_at (TIMESTAMPS)
- Indexes: INDEX(is_active, starts_at, ends_at) as idx_campaign_active_dates, INDEX(type, is_active) as idx_campaign_type_active, INDEX(threshold_scope)

---
## Content & Settings Management

### Table: `blog_categories`
- Purpose: Blog taxonomy.
- Columns:
  - id (BIGINT, PK)
  - name (VARCHAR)
  - slug (VARCHAR unique)
  - description (TEXT nullable)
  - parent_id (BIGINT nullable) FK -> blog_categories(id) CASCADE
  - meta_title/meta_description/meta_keywords (from meta tags trait)
  - icon (VARCHAR nullable)
  - created_at/updated_at (TIMESTAMPS)

### Table: `blog_posts`
- Purpose: Blog posts.
- Columns:
  - id (BIGINT, PK)
  - title (VARCHAR)
  - slug (VARCHAR unique)
  - body (LONGTEXT)
  - excerpt (TEXT)
  - author_id (BIGINT nullable) FK -> staff(id) SET NULL
  - status (VARCHAR default draft, indexed)
  - published_at (TIMESTAMP nullable)
  - read_time_minutes (INT)
  - is_featured (BOOLEAN default false, indexed)
  - meta_title/meta_description/meta_keywords (from meta tags trait)
  - main_productable_type/main_productable_id (nullable morphs)
  - thumbnail_url (VARCHAR nullable)
  - review_count (INT default 0)
  - average_rating (DECIMAL(3,2) default 0.0)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: INDEX(is_featured), INDEX(published_at), INDEX(status, published_at)

### Pivot: `blog_post_category`
- Columns: id (PK), blog_post_id (FK), blog_category_id (FK), timestamps

### Pivot: `blog_post_productables`
- Columns: id (PK), blog_post_id (FK), productable_type/productable_id (morphs), timestamps

### Table: `settings`
- Purpose: Key-value global settings.
- Columns:
  - id (BIGINT, PK)
  - key (VARCHAR unique)
  - value (JSON)
  - type (VARCHAR default 'json')
  - group (VARCHAR nullable, indexed)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: UNIQUE(key), INDEX(group)

### Table: `home_page_blocks`
- Purpose: Home page content blocks.
- Columns: id (PK), type, title, location, content (JSON), order (INT), is_active (BOOLEAN), timestamps

### Table: `sliders`
- Purpose: Slider images.
- Columns: id (PK), title, caption (nullable), image_url (nullable), image_alt (nullable), link (nullable), order (INT default 0), timestamps

### Table: `student_stories`
- Purpose: Student testimonials.
- Columns: id (PK), student_name, course_name, course_url, story_text, is_visible (BOOLEAN), display_order (INT), timestamps

### Table: `contact_us_requests`
- Purpose: Captured messages from the public contact form.
- Columns: id (PK), full_name, phone (nullable), subject, email (nullable), message (TEXT), timestamps

### Table: `collaboration_requests`
- Purpose: Requests from users to collaborate.
- Columns: id (PK), full_name, phone, email, message (TEXT), timestamps

### Table: `partners`
- Purpose: Logos/links of partner organizations displayed on site.
- Columns: id (PK), title, caption (nullable), image_url (nullable), image_alt (nullable), image_id (BIGINT nullable), url (nullable), show_in (VARCHAR default 'home'), order (INT default 0), is_active (BOOLEAN default false), timestamps

---
## System & Auditing

### Table: `admin_action_logs`
- Purpose: Staff action audit trail.
- Columns:
  - id (BIGINT, PK)
  - admin_id (BIGINT) FK -> staff(id) RESTRICT
  - action_type (VARCHAR(50))
  - resource_type (VARCHAR nullable), resource_id (BIGINT nullable)
  - route_name (VARCHAR), http_method (VARCHAR(10))
  - request_data (JSONB nullable)
  - response_status (SMALLINT)
  - ip_address (IP ADDRESS)
  - user_agent (TEXT nullable)
  - session_id (VARCHAR nullable)
  - risk_level (VARCHAR default 'low')
  - metadata (JSONB nullable)
  - created_at/updated_at (TIMESTAMPS)
- Indexes: INDEX(admin_id, created_at), INDEX(action_type, created_at), INDEX(resource_type, resource_id), INDEX(risk_level, created_at), INDEX(route_name), INDEX(ip_address)

### Table: `import_runs`
- Purpose: Immutable record of one uploaded import spreadsheet, its stored private file and its preview totals.
- Columns:
  - id (BIGINT, PK)
  - uuid (UUID, unique) — public identifier
  - resource (VARCHAR, indexed) — registered spreadsheet resource (`users`)
  - identity_key (VARCHAR, nullable) — `phone` | `email` for users; NULL for imports without identity matching. The create-table migration keeps INDEX(identity_key).
  - status (VARCHAR, indexed) — `preview_ready` | `processing` | `completed` | `completed_with_provider_failures`; `approved`/`failed`/`expired` remain future lifecycle extensions
  - staff_id (BIGINT nullable) FK -> staff(id) SET NULL
  - original_filename (VARCHAR), file_path (VARCHAR nullable), file_size (BIGINT nullable), file_checksum (VARCHAR(64) nullable)
  - rows_total / rows_valid / rows_invalid (INTEGER, default 0)
  - created_count / updated_count / provider_queued_count (INTEGER, default 0), approved_at (TIMESTAMP nullable)
  - error_report_path (VARCHAR nullable), error_report_fingerprint (VARCHAR(64) nullable) — private local report reference and sanitized content revision; cleared when no qualifying errors remain
  - artifacts_expires_at (TIMESTAMP, indexed) — absolute deadline for run upload, snapshots, and report; terminal approval/provider completion resets the configured retention window
  - artifacts_redacted_at (TIMESTAMP nullable, indexed) — set only after cleanup deletes private files and scrubs snapshots, so successful runs leave the bounded cleanup queue while failed deletions remain retryable
  - artifacts_cleanup_retry_after (TIMESTAMP nullable, indexed) — delays a failed run cleanup attempt so untouched expired runs can be processed before retry
- created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id), UNIQUE(uuid), INDEX(resource), INDEX(identity_key), INDEX(status), INDEX(resource, status), INDEX(artifacts_expires_at), INDEX(artifacts_redacted_at), INDEX(artifacts_cleanup_retry_after)
- `file_path` and `error_report_path` are nullable from creation and are cleared during cleanup; row `data` and outcome value columns are nullable so expired snapshots can be redacted while row shells remain

### `import_export_artifacts`
- `id` primary key; `resource` indexed; `artifact_uuid` unique; `path` unique; `expires_at` indexed; nullable `cleanup_retry_after` indexed; timestamps
- One row tracks each private export until physical deletion succeeds. No arbitrary storage path or public URL is persisted.
- Indexes: PK(id), UNIQUE(artifact_uuid), UNIQUE(path), INDEX(resource), INDEX(expires_at), INDEX(cleanup_retry_after)
- created_at/updated_at (TIMESTAMPS)

### Table: `import_run_rows`
- Purpose: One validated row of an import run, with its preview outcome and normalized resource values.
- Columns:
  - id (BIGINT, PK)
  - import_run_id (BIGINT) FK -> import_runs(id) CASCADE
  - row_number (INTEGER) — spreadsheet row
  - identity_value (VARCHAR nullable) — normalized identity
  - target_resource_id (VARCHAR nullable) — internal resource identifier captured at preview for safe update revalidation
  - action (VARCHAR nullable) — `create` | `update`
  - is_valid (BOOLEAN)
  - errors (JSONB nullable) — `[{field, code, message}]`
  - data (JSONB nullable) — immutable normalized resource-owned preview (no credentials), cleared after retention expires
  - local_resource_id (VARCHAR nullable) — resource ID created/updated at approval, separate from the preview target
  - local_result_data (JSONB nullable) — safe committed resource payload, including User ID; no password
  - providers (JSONB nullable) — provider-keyed `{status, message, attempts}` outcomes
  - created_at/updated_at (TIMESTAMPS)
- Indexes: PK(id), INDEX(action), UNIQUE(import_run_id, row_number), INDEX(import_run_id, identity_value)

### Table: `user_provider_accounts`
- Purpose: Durable per-User/provider standalone account outcome used across Import Runs.
- Columns: id (BIGINT PK), user_id (BIGINT FK -> users(id) CASCADE), provider (VARCHAR: moodle/ims/niliroom/skyroom), idempotency_key (VARCHAR), status (VARCHAR: pending/processing/succeeded/ambiguous), created_at/updated_at (TIMESTAMPS).
- Indexes/constraints: PK(id), UNIQUE(user_id, provider), UNIQUE(idempotency_key), INDEX(status). All enum-like values use strings, not native database enums.

### Table: `reviews`
- Purpose: User reviews for entities.
- Columns:
  - id (BIGINT, PK)
  - user_id (BIGINT) FK -> users(id) CASCADE
  - reviewable_type/reviewable_id (morphs)
  - rating (TINYINT nullable)
  - title (VARCHAR)
  - comment (TEXT)
  - status (VARCHAR default pending, indexed)
  - is_featured (BOOLEAN)

  ---

  ## Behavior Clarifications
  - Capacity checks occur at checkout time using `product_delivery_options.capacity` against `enrolled_count`.
  - Discount counters: `discount_promotions.total_usage_count` and `discount_coupons.usage_count` increment only on successful checkout.
  - Payments: `payments.status` transitions from `PENDING` to `COMPLETED` via gateway verification; re-verification on non-pending payments is rejected.
  - Orders: `orders.applied_cart_discounts_json` and `order_items.applied_discount_details_json` store immutable snapshots of applied discounts at checkout.
  - created_at/updated_at (TIMESTAMPS)
- Indexes: INDEX(user_id, reviewable_type, reviewable_id), INDEX(status, is_featured), INDEX(reviewable_type, reviewable_id, status, is_featured), INDEX(reviewable_type, reviewable_id, status)

### Table: `sms_logs`
- Purpose: Outbound SMS logs.
- Columns: id (PK), status (INT), data (JSONB nullable), content (TEXT nullable), type (VARCHAR), to (JSONB), from (VARCHAR), sent_at (DATETIME), timestamps

---
## Pivot & Relationship Tables

### Table: `assetables`
- Purpose: Attach digital assets to any model.
- Columns: digital_asset_id (FK), assetable_id, assetable_type
- Keys: PRIMARY(digital_asset_id, assetable_id, assetable_type); INDEX(digital_asset_id, assetable_type)

### Table: `categorizables`
- Purpose: Attach categories to any model.
- Columns: id (PK), category_id (FK), categorizable_id, categorizable_type, good_for_start (BOOLEAN default false)
- Keys: UNIQUE(category_id, categorizable_id, categorizable_type); INDEX(categorizable_id, categorizable_type)

### Table: `product_delivery_option_teacher`
- See above (pivot section in products).

### Table: `blog_post_category`
- See above (content section).

### Table: `blog_post_productables`
- See above (content section).

### Table: `mediables` (plank/laravel-mediable)
- Columns: media_id (FK -> media.id), mediable_id, mediable_type, tag (indexed), order (indexed)
- Keys: PRIMARY(media_id, mediable_type, mediable_id, tag); INDEX(mediable_id, mediable_type)

### Table: `media` (plank/laravel-mediable)
- Columns: id, disk, directory, filename, extension, mime_type, aggregate_type (indexed), size, variant_name (nullable), original_media_id (nullable FK -> media.id), timestamps
- Keys: UNIQUE(disk, directory, filename, extension)

### Spatie Permission Tables
- roles: id (PK), team_foreign_key (nullable, indexed when teams enabled), name (unique with guard), label, guard_name, timestamps
- permissions: id (PK), name (unique with guard), guard_name, timestamps
- model_has_roles: PK(role_id, model_id, model_type[, team_fk]), indexes on model and team
- model_has_permissions: PK(permission_id, model_id, model_type[, team_fk]), indexes on model and team
- role_has_permissions: PK(permission_id, role_id)

---
## Infrastructure & Framework Tables

- cache: key (PRIMARY), value (MEDIUMTEXT), expiration (INT)
- cache_locks: key (PRIMARY), owner (STRING), expiration (INT)
- jobs: id (PK), queue (indexed), payload, attempts, reserved_at, available_at, created_at
- job_batches: id (PRIMARY), name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at
- failed_jobs: id (PK), uuid (UNIQUE), connection, queue, payload, exception, failed_at
- personal_access_tokens: id (PK), tokenable_type/id, name, token (UNIQUE), abilities (TEXT nullable), last_used_at, expires_at, timestamps
- webhook_calls: id (PK), name, url, headers (JSON nullable), payload (JSON nullable), exception (TEXT nullable), timestamps
- telescope_entries: sequence (PRIMARY), uuid (UNIQUE), batch_id (INDEXED), family_hash (INDEXED), should_display_on_index, type (20), content (LONGTEXT), created_at (INDEXED), plus INDEX(type, should_display_on_index)
- telescope_entries_tags: PRIMARY(entry_uuid, tag), INDEX(tag), FK(entry_uuid) -> telescope_entries(uuid)
- telescope_monitoring: tag (PRIMARY)

### Table: `bundle_purchases`
- Purpose: Immutable Customer-facing grouping record for a purchased Bundle PDO.
- Columns: `id`, `order_id` (FK, cascade), `product_delivery_option_id` (FK, restrict), `bundle_name`, `product_name`, `name`, `sku`, `base_value`, `selling_price`, `composition_version`, `checkout_status`, `product_data_snapshot_json` (JSONB), timestamps.
- Constraints: UNIQUE(`order_id`, `product_delivery_option_id`). Component `order_items.bundle_purchase_id` references this table with restricted deletion.
